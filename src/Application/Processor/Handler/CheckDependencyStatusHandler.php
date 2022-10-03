<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Processor\Handler;

use Composer\Semver\Semver;
use Courier\Message\CommandInterface;
use Courier\Processor\Handler\HandlerResultEnum;
use Courier\Processor\Handler\InvokeHandlerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use PackageHealth\PHP\Application\Message\Command\CheckDependencyStatusCommand;
use PackageHealth\PHP\Domain\Dependency\DependencyRepositoryInterface;
use PackageHealth\PHP\Domain\Dependency\DependencyStatusEnum;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Checks if $dependency status can be updated (and updates it)
 *
 * @see PackageHealth\PHP\Application\Processor\Listener\Dependency\DependencyCreatedListener
 */
final class CheckDependencyStatusHandler implements InvokeHandlerInterface {
  private DependencyRepositoryInterface $dependencyRepository;
  private PackageRepositoryInterface $packageRepository;
  private LoggerInterface $logger;

  public function __construct(
    DependencyRepositoryInterface $dependencyRepository,
    PackageRepositoryInterface $packageRepository,
    LoggerInterface $logger
  ) {
    $this->dependencyRepository = $dependencyRepository;
    $this->packageRepository    = $packageRepository;
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

    if (($command instanceof CheckDependencyStatusCommand) === false) {
      $this->logger->critical(
        sprintf(
          'Invalid command argument for CheckDependencyStatusHandler: "%s"',
          $command::class
        )
      );

      return HandlerResultEnum::REJECT;
    }

    try {
      $dependency = $command->getDependency();

      $dependencyName = $dependency->getName();

      // guard for job duplication
      $uniqueId = sprintf(
        '%s@%d',
        $dependencyName,
        $dependency->getVersionId()
      );
      $timestamp = ($attributes['timestamp'] ?? new DateTimeImmutable())->getTimestamp();
      if (
        $command->forceExecution() === false &&
        $lastUniqueId === $uniqueId &&
        $lastTimestamp > 0 &&
        $timestamp - $lastTimestamp < 10
      ) {
        $this->logger->debug(
          'Check dependency status handler: Skipping duplicated job',
          [
            'dependency'    => $dependencyName,
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
        'Check dependency status handler',
        [
          'dependency' => $dependencyName,
          'versionId'  => $dependency->getVersionId()
        ]
      );

      if ($dependency->getConstraint() === 'self.version') {
        // need to find out how to handle this
        return HandlerResultEnum::REJECT;
      }

      $packageCol = $this->packageRepository->find(
        [
          'name' => $dependencyName
        ],
        1
      );

      if ($packageCol->isEmpty()) {
        // this package is either not discovered yet or is not on packagist at all
        return HandlerResultEnum::ACCEPT;
      }

      $package = $packageCol->first();
      if ($package->getLatestVersion() === '') {
        return HandlerResultEnum::ACCEPT;
      }

      $dependency = $dependency->withStatus(
        Semver::satisfies($package->getLatestVersion(), $dependency->getConstraint()) ?
          DependencyStatusEnum::UpToDate :
          DependencyStatusEnum::Outdated
      );

      $dependency = $this->dependencyRepository->update($dependency);

      return HandlerResultEnum::ACCEPT;
    } catch (Exception $exception) {
      $this->logger->error(
        $exception->getMessage(),
        [
          'package'   => $dependencyName,
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
