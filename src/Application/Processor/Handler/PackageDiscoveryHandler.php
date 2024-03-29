<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Processor\Handler;

use Composer\Semver\VersionParser;
use Courier\Message\CommandInterface;
use Courier\Processor\Handler\HandlerResultEnum;
use Courier\Processor\Handler\InvokeHandlerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use PackageHealth\PHP\Application\Message\Command\PackageDiscoveryCommand;
use PackageHealth\PHP\Application\Service\Packagist;
use PackageHealth\PHP\Domain\Dependency\DependencyRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use PackageHealth\PHP\Domain\Version\Version;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionStatusEnum;
use Psr\Log\LoggerInterface;

/**
 * Retrieves package metadata from packagist.org
 *  - List of avaialble versions (tagged releases and development branches);
 *  - List of required dependencies (runtime and dev) per version.
 *
 * @see PackageHealth\PHP\Application\Console\Packagist\GetListCommand
 * @see PackageHealth\PHP\Application\Console\Packagist\GetUpdatesCommand
 */
class PackageDiscoveryHandler implements InvokeHandlerInterface {
  private DependencyRepositoryInterface $dependencyRepository;
  private PackageRepositoryInterface $packageRepository;
  private VersionRepositoryInterface $versionRepository;
  private Packagist $packagist;
  private LoggerInterface $logger;

  private function findLatestVersion(array $releases): string {
    foreach ($releases as $release) {
      if (VersionParser::parseStability($release['version_normalized']) === 'stable') {
        return $release['version'];
      }
    }

    return '';
  }

  public function __construct(
    DependencyRepositoryInterface $dependencyRepository,
    PackageRepositoryInterface $packageRepository,
    VersionRepositoryInterface $versionRepository,
    Packagist $packagist,
    LoggerInterface $logger
  ) {
    $this->dependencyRepository = $dependencyRepository;
    $this->packageRepository    = $packageRepository;
    $this->versionRepository    = $versionRepository;
    $this->packagist            = $packagist;
    $this->logger               = $logger;
  }

  /**
   * @param array{
   *   appId?: string,
   *   correlationId?: string,
   *   expiration?: string,
   *   headers?: array<string, mixed>,
   *   isRedelivery?: bool,
   *   messageId?: string,
   *   priority?: \Courier\Message\EnvelopePriorityEnum,
   *   replyTo?: string,
   *   timestamp?: \DateTimeImmutable|null,
   *   type?: string,
   *   userId?: string
   * } $attributes
   */
  public function __invoke(CommandInterface $command, array $attributes = []): HandlerResultEnum {
    static $lastUniqueId  = '';
    static $lastTimestamp = 0;

    if (($command instanceof PackageDiscoveryCommand) === false) {
      $this->logger->critical(
        sprintf(
          'Invalid command argument for PackageDiscoveryHandler: "%s"',
          $command::class
        )
      );

      return HandlerResultEnum::REJECT;
    }

    try {
      switch ($command->workOffline()) {
        case true:
          $this->packagist->workOffline();
          break;
        case false:
          $this->packagist->workOnline();
          break;
      }

      $packageName = $command->getPackageName();

      // guard for job duplication
      $uniqueId  = $packageName;
      $timestamp = ($attributes['timestamp'] ?? new DateTimeImmutable())->getTimestamp();
      if (
        $command->forceExecution() === false &&
        $lastUniqueId === $uniqueId &&
        $lastTimestamp > 0 &&
        $timestamp - $lastTimestamp < 10
      ) {
        $this->logger->debug(
          'Package discovery handler: Skipping duplicated job',
          [
            'timestamp'     => $timestamp,
            'lastUniqueId'  => $lastUniqueId,
            'lastTimestamp' => (new DateTimeImmutable())->setTimestamp($lastTimestamp)->format(DateTimeInterface::ATOM)
          ]
        );

        // shoud just accept so that it doesn't show as churn?
        return HandlerResultEnum::REJECT;
      }

      // update deduplication guards
      $lastUniqueId  = $uniqueId;
      $lastTimestamp = $timestamp;

      $this->logger->info(
        'Package discovery handler',
        ['package' => $packageName]
      );

      $packageCol = $this->packageRepository->find(
        [
          'name' => $packageName
        ],
        1
      );

      $package = match ($packageCol->isEmpty()) {
        true  => $this->packageRepository->create($packageName),
        false => $packageCol->first()
      };

      $pkgs = [
        // dev versions
        "{$packageName}~dev",
        // tagged releses
        $packageName
      ];

      // list all package releases
      $versionCol = $this->versionRepository->find(
        [
          'package_id' => $package->getId()
        ]
      );

      $releaseList = $versionCol
        ->filter(
          static function (Version $version): bool {
            return $version->isRelease();
          }
        )
        ->map(
          static function (Version $version): string {
            return $version->getNormalized();
          }
        )
        ->toArray();

      $developList = $versionCol
        ->filter(
          static function (Version $version): bool {
            return $version->isRelease() === false;
          }
        )
        ->map(
          static function (Version $version): string {
            return $version->getNormalized();
          }
        )
        ->toArray();

      $this->logger->debug(
        'Preloaded version list',
        [
          'release' => count($releaseList),
          'develop' => count($developList)
        ]
      );

      foreach ($pkgs as $pkg) {
        $metadata = $this->packagist->getPackageMetadataVersion2($pkg);
        if (count($metadata) === 0) {
          $this->logger->debug('Empty package metadata', ['package' => $pkg]);

          continue;
        }

        // ensure latest tagged version is set when importing a package for the first time
        if ($pkg === $packageName && $package->getLatestVersion() === '') {
          $package = $package->withLatestVersion(
            $this->findLatestVersion($metadata)
          );
        }

        $package = $package
          ->withDescription(
            $metadata[0]['description'] ?? $package->getDescription()
          )
          ->withUrl(
            preg_replace(
              '/\.git$/',
              '',
              $metadata[0]['source']['url'] ?? $package->getUrl()
            )
          );
        $package = $this->packageRepository->update($package);

        $this->logger->debug(
          'Processing release list',
          [
            'package' => $pkg,
            'count'   => count($metadata)
          ]
        );

        foreach (array_reverse($metadata) as $release) {
          // exclude branches from tagged releases (https://getcomposer.org/doc/articles/versions.md#branches)
          $isBranch = preg_match('/^dev-|-dev$/', $release['version']) === 1;

          if (
            in_array($release['version_normalized'], $releaseList, true) === true ||
            in_array($release['version_normalized'], $developList, true) === true
          ) {
            // skip versions that have been previously discovered
            continue;
          }

          // double check that the current release is not registered
          // find by the unique constraint (package_id, number)
          $versionCol = $this->versionRepository->find(
            [
              'package_id' => $package->getId(),
              'number'     => $release['version']
            ],
            1
          );

          $version = match ($versionCol->isEmpty()) {
            true  => $this->versionRepository->create(
              $package->getId(),
              $release['version'],
              $release['version_normalized'],
              $isBranch === false,
              VersionStatusEnum::Unknown,
              new DateTimeImmutable($release['time'] ?? 'now')
            ),
            false => $versionCol->first() // should never happen, but just in case
          };

          // track "require" dependencies
          $filteredRequire = array_filter(
            $release['require'] ?? [],
            [PackageValidator::class, 'isValid'],
            ARRAY_FILTER_USE_KEY
          );

          // flag packages without require dependencies with VersionStatusEnum::NoDeps
          if (empty($filteredRequire)) {
            $version = $version->withStatus(VersionStatusEnum::NoDeps);
            $version = $this->versionRepository->update($version);
          }

          $this->logger->debug(
            'Processing "require" dependencies',
            [
              'package' => $pkg,
              'version' => $release['version'],
              'count'   => count($filteredRequire)
            ]
          );

          foreach ($filteredRequire as $dependencyName => $constraint) {
            if ($constraint === 'self.version') {
              $constraint = $release['version'];
            }

            // find by the unique constraint (version_id, name, development)
            $dependencyCol = $this->dependencyRepository->find(
              [
                'version_id'  => $version->getId(),
                'name'        => $dependencyName,
                'development' => false
              ],
              1
            );

            // create previously missing dependency record
            if ($dependencyCol->isEmpty()) {
              $this->dependencyRepository->create(
                $version->getId(),
                $dependencyName,
                $constraint,
                false
              );
            }
          }

          // track "require-dev" dependencies
          $filteredRequireDev = array_filter(
            $release['require-dev'] ?? [],
            [PackageValidator::class, 'isValid'],
            ARRAY_FILTER_USE_KEY
          );
          if (empty($filteredRequireDev)) {
            continue;
          }

          $this->logger->debug(
            'Processing "require-dev" dependencies',
            [
              'package' => $pkg,
              'version' => $release['version'],
              'count'   => count($filteredRequireDev)
            ]
          );

          foreach ($filteredRequireDev as $dependencyName => $constraint) {
            if ($constraint === 'self.version') {
              $constraint = $release['version'];
            }

            // find by the unique constraint (version_id, name, development)
            $dependencyCol = $this->dependencyRepository->find(
              [
                'version_id'  => $version->getId(),
                'name'        => $dependencyName,
                'development' => true
              ],
              1
            );

            // create previously missing dependency record
            if ($dependencyCol->isEmpty()) {
              $this->dependencyRepository->create(
                $version->getId(),
                $dependencyName,
                $constraint,
                true
              );
            }
          }
        }

        // clean "development releases" (aka. branches)
        $currReleases = array_column($metadata, 'version_normalized');
        $releaseDiff  = array_diff($developList, $currReleases);
        if (count($releaseDiff)) {
          $this->logger->debug(
            'Cleaning up invalid branches',
            [
              'package' => $pkg,
              'count'   => count($releaseDiff)
            ]
          );
          foreach ($releaseDiff as $release) {
            $versionCol = $this->versionRepository->find(
              [
                'package_id' => $package->getId(),
                'normalized' => $release
              ],
              1
            );

            if ($versionCol->isEmpty() === true) {
              // if this branch has been removed by someone else, just skip
              continue;
            }

            $version = $versionCol->first();
            // this will remove dependencies due to "ON DELETE CASCADE" defined in the FKey
            $this->versionRepository->delete($version);
          }
        }
      }

      return HandlerResultEnum::ACCEPT;
    } catch (Exception $exception) {
      $this->logger->error(
        $exception->getMessage(),
        [
          'package'   => $command->getPackageName(),
          'exception' => [
            'file'  => $exception->getFile(),
            'line'  => $exception->getLine(),
            'trace' => $exception->getTrace()
          ]
        ]
      );

      // reject a command that has been requeued
      if ($attributes['isRedelivery'] ?? false) {
        return HandlerResultEnum::REJECT;
      }

      return HandlerResultEnum::REQUEUE;
    }
  }
}
