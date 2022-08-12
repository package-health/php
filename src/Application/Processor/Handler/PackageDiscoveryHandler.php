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
use PackageHealth\PHP\Domain\Dependency\DependencyStatusEnum;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
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

  private function filterDeps(array $deps): array {
    return array_filter(
      $deps,
      static function (string $key): bool {
        return preg_match('/^(php|hhvm|ext-.*|lib-.*|pear-.*)$/', $key) !== 1 &&
          preg_match('/^[^\/]+\/[^\/]+$/', $key) === 1;
      },
      ARRAY_FILTER_USE_KEY
    );
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

      return HandlerResultEnum::Reject;
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
        return HandlerResultEnum::Reject;
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
            false => $versionCol->first()
          };

          // track "require" dependencies
          $filteredRequire = $this->filterDeps($release['require'] ?? []);

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
              // need to find out how to handle this
              continue;
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
          $filteredRequireDev = $this->filterDeps($release['require-dev'] ?? []);
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
              // need to find out how to handle this
              continue;
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
      }

      return HandlerResultEnum::Accept;
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
        return HandlerResultEnum::Reject;
      }

      return HandlerResultEnum::Requeue;
    }
  }
}
