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
use PackageHealth\PHP\Application\Message\Command\PackagePurgeCommand;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Purges a package and all of its releases and dependencies.
 *
 * @see PackageHealth\PHP\Application\Console\Packagist\GetListCommand
 */
class PackagePurgeHandler implements InvokeHandlerInterface {
  private PackageRepositoryInterface $packageRepository;
  private LoggerInterface $logger;

  public function __construct(
    PackageRepositoryInterface $packageRepository,
    LoggerInterface $logger
  ) {
    $this->packageRepository = $packageRepository;
    $this->logger            = $logger;
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

    if (($command instanceof PackagePurgeCommand) === false) {
      $this->logger->critical(
        sprintf(
          'Invalid command argument for PackagePurgeHandler: "%s"',
          $command::class
        )
      );

      return HandlerResultEnum::REJECT;
    }

    try {
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
          'Package purge handler: Skipping duplicated job',
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
        'Package purge handler',
        ['package' => $packageName]
      );

      $packageCol = $this->packageRepository->find(
        [
          'name' => $packageName
        ],
        1
      );

      if ($packageCol->isEmpty()) {
        $this->logger->info(
          'Package not found',
          ['package' => $packageName]
        );

        return HandlerResultEnum::ACCEPT;
      }

      $package = $packageCol->first();
      // this will remove versions *and* dependencies due to "ON DELETE CASCADE" defined in the FKey
      $this->packageRepository->delete($package);

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
