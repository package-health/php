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
use PackageHealth\PHP\Application\Message\Command\UpdateDependencyStatusCommand;
use PackageHealth\PHP\Domain\Dependency\DependencyRepositoryInterface;
use PackageHealth\PHP\Domain\Dependency\DependencyStatusEnum;
use Psr\Log\LoggerInterface;

/**
 * Updates all dependencies that "require" or "require-dev" $package
 *
 * @see PackageHealth\PHP\Application\Processor\Listener\Package\PackageUpdatedListener
 */
class UpdateDependencyStatusHandler implements InvokeHandlerInterface {
  private DependencyRepositoryInterface $dependencyRepository;
  private LoggerInterface $logger;

  public function __construct(
    DependencyRepositoryInterface $dependencyRepository,
    LoggerInterface $logger
  ) {
    $this->dependencyRepository = $dependencyRepository;
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

    if (($command instanceof UpdateDependencyStatusCommand) === false) {
      $this->logger->critical(
        sprintf(
          'Invalid command argument for UpdateDependencyStatusHandler: "%s"',
          $command::class
        )
      );

      return HandlerResultEnum::Reject;
    }

    try {
      $package = $command->getPackage();

      $packageName = $package->getName();

      // guard for job duplication
      $uniqueId = sprintf(
        '%s@%s',
        $packageName,
        $package->getLatestVersion()
      );
      $timestamp = ($attributes['timestamp'] ?? new DateTimeImmutable())->getTimestamp();
      if (
        $command->forceExecution() === false &&
        $lastUniqueId === $uniqueId &&
        $lastTimestamp > 0 &&
        $timestamp - $lastTimestamp < 10
      ) {
        $this->logger->debug(
          'Update dependency status handler: Skipping duplicated job',
          [
            'package'       => $packageName,
            'timestamp'     => $timestamp,
            'uniqueId'      => $uniqueId,
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
        'Update dependency status handler',
        [
          'package' => $packageName,
          'version' => $package->getLatestVersion()
        ]
      );

      if ($package->getLatestVersion() === '') {
        return HandlerResultEnum::Reject;
      }

      $limit = 500;
      $offset = 0;
      do {
        $dependencyCol = $this->dependencyRepository->find(
          [
            'name' => $packageName
          ],
          $limit,
          $offset
        );

        foreach ($dependencyCol as $dependency) {
          if ($dependency->getConstraint() === 'self.version') {
            // need to find out how to handle this
            continue;
          }

          $dependency = $dependency->withStatus(
            Semver::satisfies($package->getLatestVersion(), $dependency->getConstraint()) ?
              DependencyStatusEnum::UpToDate :
              DependencyStatusEnum::Outdated
          );

          $this->dependencyRepository->update($dependency);
        }
        
        $offset += $limit;
      } while ($dependencyCol->isEmpty() === false);
        
      return HandlerResultEnum::Accept;
    } catch (Exception $exception) {
      $this->logger->error(
        $exception->getMessage(),
        [
          'package'   => $command->getPackage()->getName(),
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
