<?php
declare(strict_types = 1);

namespace App\Application\Processor\Handler;

use App\Application\Handler\DependencyUpdatedEvent;
use App\Application\Message\Command\PackageDiscoveryCommand;
use App\Application\Message\Event\Dependency\DependencyCreatedEvent;
use App\Application\Message\Event\Package\PackageUpdatedEvent;
use App\Application\Message\Event\Version\VersionCreatedEvent;
use App\Application\Message\Event\Version\VersionUpdatedEvent;
use App\Application\Service\Packagist;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use App\Domain\Package\PackageRepositoryInterface;
use App\Domain\Version\VersionRepositoryInterface;
use App\Domain\Version\VersionStatusEnum;
use Courier\Client\Producer\ProducerInterface;
use Courier\Message\CommandInterface;
use Courier\Processor\Handler\HandlerResultEnum;
use Courier\Processor\Handler\InvokeHandlerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Psr\Log\LoggerInterface;

class PackageDiscoveryHandler implements InvokeHandlerInterface {
  private DependencyRepositoryInterface $dependencyRepository;
  private PackageRepositoryInterface $packageRepository;
  private VersionRepositoryInterface $versionRepository;
  private ProducerInterface $producer;
  private Packagist $packagist;
  private LoggerInterface $logger;

  public function __construct(
    DependencyRepositoryInterface $dependencyRepository,
    PackageRepositoryInterface $packageRepository,
    VersionRepositoryInterface $versionRepository,
    ProducerInterface $producer,
    Packagist $packagist,
    LoggerInterface $logger
  ) {
    $this->dependencyRepository = $dependencyRepository;
    $this->packageRepository    = $packageRepository;
    $this->versionRepository    = $versionRepository;
    $this->producer             = $producer;
    $this->packagist            = $packagist;
    $this->logger               = $logger;
  }

  /**
   * Retrieves package metadata from packagist.org
   *  - List of avaialble versions
   *  - List of required dependencies per version
   *
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
      $package = $command->getPackage();

      $packageName = $package->getName();

      // check for job duplication
      $uniqueId  = $packageName;
      $timestamp = ($attributes['timestamp'] ?? new DateTimeImmutable())->getTimestamp();
      if (
        $command->forceExecution() === false &&
        $lastUniqueId === $uniqueId &&
        $timestamp - $lastTimestamp < 10
      ) {
        $this->logger->debug(
          'Package discovery handler: Skipping duplicated job',
          [
            'lastUniqueId'  => $lastUniqueId,
            'lastTimestamp' => (new DateTimeImmutable)->setTimestamp($lastTimestamp)->format(DateTimeInterface::ATOM)
          ]
        );

        // shoud just accept so that it doesn't show as churn?
        return HandlerResultEnum::Reject;
      }

      $this->logger->info(
        'Package discovery handler',
        ['package' => $packageName]
      );

      $pkgs = [
        // dev versions
        "${packageName}~dev",
        // tagged releses
        $packageName
      ];

      foreach ($pkgs as $pkg) {
        $metadata = $this->packagist->getPackageMetadataVersion2($pkg);
        if (count($metadata) === 0) {
          $this->logger->debug('Empty package metadata', ['package' => $pkg]);

          continue;
        }

        $package = $package
          ->withDescription($metadata[0]['description'] ?? '')
          ->withUrl($metadata[0]['source']['url'] ?? '');
        if ($package->isDirty()) {
          $package = $this->packageRepository->update($package);

          $this->producer->sendEvent(
            new PackageUpdatedEvent($package)
          );
        }

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

          // find by the unique constraint (number, package_name)
          $versionCol = $this->versionRepository->find(
            [
              'number'       => $release['version'],
              'package_name' => $packageName
            ]
          );

          $version = $versionCol[0] ?? null;
          if ($version === null) {
            $version = $this->versionRepository->create(
              $release['version'],
              $release['version_normalized'],
              $packageName,
              $isBranch === false,
              VersionStatusEnum::Unknown,
              new DateTimeImmutable($release['time'] ?? 'now')
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
      }

      // update deduplication guards
      $lastUniqueId  = $uniqueId;
      $lastTimestamp = $timestamp;

      return HandlerResultEnum::Accept;
    } catch (Exception $exception) {
      $this->logger->error(
        $exception->getMessage(),
        [
          'package'   => $packageName,
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
