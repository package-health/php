<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Processor\Handler;

use Courier\Message\CommandInterface;
use Courier\Processor\Handler\HandlerResultEnum;
use Courier\Processor\Handler\InvokeHandlerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use PackageHealth\PHP\Application\Message\Command\UpdateVersionStatusCommand;
use PackageHealth\PHP\Domain\Dependency\Dependency;
use PackageHealth\PHP\Domain\Dependency\DependencyRepositoryInterface;
use PackageHealth\PHP\Domain\Dependency\DependencyStatusEnum;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionStatusEnum;
use Psr\Log\LoggerInterface;

/**
 * Updates the version status that requires $dependency
 *
 * @see PackageHealth\PHP\Application\Processor\Listener\Dependency\DependencyUpdatedListener
 */
final class UpdateVersionStatusHandler implements InvokeHandlerInterface {
  private VersionRepositoryInterface $versionRepository;
  private DependencyRepositoryInterface $dependencyRepository;
  private LoggerInterface $logger;

  public function __construct(
    VersionRepositoryInterface $versionRepository,
    DependencyRepositoryInterface $dependencyRepository,
    LoggerInterface $logger
  ) {
    $this->versionRepository    = $versionRepository;
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

    if (($command instanceof UpdateVersionStatusCommand) === false) {
      $this->logger->critical(
        sprintf(
          'Invalid command argument for UpdateVersionStatusHandler: "%s"',
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
          'Update version status handler: Skipping duplicated job',
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
        'Update version status handler',
        [
          'dependency' => $dependencyName,
          'versionId'  => $dependency->getVersionId()
        ]
      );

      $version = $this->versionRepository->get($dependency->getVersionId());
      $reqDeps = $this->dependencyRepository->find(
        [
          'version_id'  => $dependency->getVersionId(),
          'development' => false
        ]
      );

      $statuses = $reqDeps
        ->map(
          static function (Dependency $dependency): DependencyStatusEnum {
            return $dependency->getStatus();
          }
        )
        ->toArray();

      $version = $version->withStatus(
        match (true) {
          in_array(DependencyStatusEnum::Insecure, $statuses, true)      => VersionStatusEnum::Insecure,
          in_array(DependencyStatusEnum::MaybeInsecure, $statuses, true) => VersionStatusEnum::MaybeInsecure,
          in_array(DependencyStatusEnum::Outdated, $statuses, true)      => VersionStatusEnum::Outdated,
          in_array(DependencyStatusEnum::Unknown, $statuses, true)       => VersionStatusEnum::Unknown,
          in_array(DependencyStatusEnum::UpToDate, $statuses, true)      => VersionStatusEnum::UpToDate,
          empty($statuses)                                               => VersionStatusEnum::NoDeps,
          default                                                        => $version->getStatus()
        }
      );

      $this->versionRepository->update($version);

      return HandlerResultEnum::ACCEPT;
    } catch (Exception $exception) {
      $this->logger->error(
        $exception->getMessage(),
        [
          'dependency' => $command->getDependency()->getName(),
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
