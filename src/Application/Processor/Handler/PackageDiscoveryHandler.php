<?php
declare(strict_types = 1);

namespace App\Application\Processor\Handler;

use App\Application\Handler\DependencyUpdatedEvent;
use App\Application\Message\Event\Dependency\DependencyCreatedEvent;
use App\Application\Message\Event\Package\PackageUpdatedEvent;
use App\Application\Message\Event\Stats\StatsCreatedEvent;
use App\Application\Message\Event\Stats\StatsUpdatedEvent;
use App\Application\Message\Event\Version\VersionCreatedEvent;
use App\Application\Message\Event\Version\VersionUpdatedEvent;
use App\Application\Service\Packagist;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use App\Domain\Package\PackageRepositoryInterface;
use App\Domain\Stats\StatsRepositoryInterface;
use App\Domain\Version\VersionRepositoryInterface;
use App\Domain\Version\VersionStatusEnum;
use Courier\Client\Producer;
use Courier\Message\CommandInterface;
use Courier\Processor\Handler\HandlerResultEnum;
use Courier\Processor\Handler\InvokeHandlerInterface;
use Psr\Log\LoggerInterface;

class PackageDiscoveryHandler implements InvokeHandlerInterface {
  private DependencyRepositoryInterface $dependencyRepository;
  private PackageRepositoryInterface $packageRepository;
  private StatsRepositoryInterface $statsRepository;
  private VersionRepositoryInterface $versionRepository;
  private Producer $producer;
  private Packagist $packagist;
  private LoggerInterface $logger;

  public function __construct(
    DependencyRepositoryInterface $dependencyRepository,
    PackageRepositoryInterface $packageRepository,
    StatsRepositoryInterface $statsRepository,
    VersionRepositoryInterface $versionRepository,
    Producer $producer,
    Packagist $packagist,
    LoggerInterface $logger
  ) {
    $this->dependencyRepository = $dependencyRepository;
    $this->packageRepository    = $packageRepository;
    $this->statsRepository      = $statsRepository;
    $this->versionRepository    = $versionRepository;
    $this->producer             = $producer;
    $this->packagist            = $packagist;
    $this->logger               = $logger;
  }

  /**
   * Retrieves package metadata from packagist.org
   *  - List of avaialble versions
   *  - List of required dependencies per version
   *  - Package statistics
   */
  public function __invoke(CommandInterface $command): HandlerResultEnum {
    try {
      $package = $command->getPackage();

      $metadata = $this->packagist->getPackageMetadataVersion1($package->getName());

      $package = $package
        ->withDescription($metadata['description'] ?? '')
        ->withUrl($metadata['repository'] ?? '');
      if ($package->isDirty()) {
        $package = $this->packageRepository->update($package);

        $this->producer->sendEvent(
          new PackageUpdatedEvent($package)
        );
      }

      $statsCol = $this->statsRepository->find(
        [
          'package_name' => $package->getName()
        ]
      );

      $stats = $statsCol[0] ?? null;
      if ($stats === null) {
        $stats = $this->statsRepository->create(
          $package->getName()
        );

        $this->producer->sendEvent(
          new StatsCreatedEvent($stats)
        );
      }

      $stats = $stats
        ->withGithubStars($metadata['github_stars'] ?? 0)
        ->withGithubWatchers($metadata['github_watchers'] ?? 0)
        ->withGithubForks($metadata['github_forks'] ?? 0)
        ->withDependents($metadata['dependents'] ?? 0)
        ->withSuggesters($metadata['suggesters'] ?? 0)
        ->withFavers($metadata['favers'] ?? 0)
        ->withTotalDownloads($metadata['downloads']['total'] ?? 0)
        ->withMonthlyDownloads($metadata['downloads']['monthly'] ?? 0)
        ->withDailyDownloads($metadata['downloads']['daily'] ?? 0);

      if ($stats->isDirty()) {
        $stats = $this->statsRepository->update($stats);

        $this->producer->sendEvent(
          new StatsUpdatedEvent($stats)
        );
      }

      // version list is empty
      if (count($metadata['versions']) === 0) {
        $this->logger->notice('Version list is empty', [$package]);

        return HandlerResultEnum::Accept;
      }

      foreach (array_reverse($metadata['versions']) as $release) {
        // exclude branches from tagged releases (https://getcomposer.org/doc/articles/versions.md#branches)
        $isBranch = preg_match('/^dev-|-dev$/', $release['version']) === 1;

        // find by the unique constraint (number, package_name)
        $versionCol = $this->versionRepository->find(
          [
            'number'       => $release['version'],
            'package_name' => $package->getName()
          ]
        );

        $version = $versionCol[0] ?? null;
        if ($version === null) {
          $version = $this->versionRepository->create(
            $release['version'],
            $release['version_normalized'],
            $package->getName(),
            $isBranch === false
          );

          $this->producer->sendEvent(
            new VersionCreatedEvent($version)
          );
        }

        // track "require" dependencies
        $filteredRequire = array_filter(
          $release['require'] ?? [],
          static function (string $key): bool {
            return preg_match('/^(php|hhvm|ext-.*|lib-.*|pear-.*)$/', $key) !== 1 &&
              preg_match('/^[^\/]+\/[^\/]+$/', $key) === 1;
          },
          ARRAY_FILTER_USE_KEY
        );

        // flag packages without require dependencies with VersionStatusEnum::NoDeps
        if (empty($filteredRequire)) {
          $version = $version->withStatus(VersionStatusEnum::NoDeps);
          $version = $this->versionRepository->update($version);

          $this->producer->sendEvent(
            new VersionUpdatedEvent($version)
          );
        }

        foreach ($filteredRequire as $dependencyName => $constraint) {
          if ($constraint === 'self.version') {
            // need to find out how to handle this
            continue;
          }

          // find by the unique constraint (version_id, name, development)
          $dependencyCol = $this->dependencyRepository->find(
            [
              'version_id'  => $version->getId(),
              'name'        => $dependencyName,
              'development' => false
            ]
          );

          $dependency = $dependencyCol[0] ?? null;
          if ($dependency === null) {
            $dependency = $this->dependencyRepository->create(
              $version->getId(),
              $dependencyName,
              $constraint,
              false
            );

            $this->producer->sendEvent(
              new DependencyCreatedEvent($dependency)
            );
          }
        }

        // track "require-dev" dependencies
        $filteredRequireDev = array_filter(
          $release['require-dev'] ?? [],
          static function (string $key): bool {
            return preg_match('/^(php|hhvm|ext-.*|lib-.*|pear-.*)$/', $key) !== 1 &&
              preg_match('/^[^\/]+\/[^\/]+$/', $key) === 1;
          },
          ARRAY_FILTER_USE_KEY
        );

        foreach ($filteredRequireDev as $dependencyName => $constraint) {
          if ($constraint === 'self.version') {
            // need to find out how to handle this
            continue;
          }

          // find by the unique constraint (version_id, name, development)
          $dependencyCol = $this->dependencyRepository->find(
            [
              'version_id'  => $version->getId(),
              'name'        => $dependencyName,
              'development' => true
            ]
          );

          $dependency = $dependencyCol[0] ?? null;
          if ($dependency === null) {
            $dependency = $this->dependencyRepository->create(
              $version->getId(),
              $dependencyName,
              $constraint,
              true
            );

            $this->producer->sendEvent(
              new DependencyCreatedEvent($dependency)
            );
          }
        }
      }

      return HandlerResultEnum::Accept;
    } catch (Exception $exception) {
      $this->logger->error($exception->getMessage(), [$exception]);

      return HandlerResultEnum::Requeue;
    }
  }
}
