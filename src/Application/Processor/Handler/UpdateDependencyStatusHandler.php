<?php
declare(strict_types = 1);

namespace App\Application\Processor\Handler;

use App\Application\Message\Event\Dependency\DependencyUpdatedEvent;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use Composer\Semver\Semver;
use Courier\Client\Producer\ProducerInterface;
use Courier\Message\CommandInterface;
use Courier\Processor\Handler\HandlerResultEnum;
use Courier\Processor\Handler\InvokeHandlerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;

class UpdateDependencyStatusHandler implements InvokeHandlerInterface {
  private DependencyRepositoryInterface $dependencyRepository;
  private ProducerInterface $producer;
  private LoggerInterface $logger;

  public function __construct(
    DependencyRepositoryInterface $dependencyRepository,
    ProducerInterface $producer,
    LoggerInterface $logger
  ) {
    $this->dependencyRepository = $dependencyRepository;
    $this->producer             = $producer;
    $this->logger               = $logger;
  }

  /**
   * Updates all dependency references that "require" or "require-dev" $package
   */
  public function __invoke(CommandInterface $command, array $attributes = []): HandlerResultEnum {
    static $lastPackage = '';
    static $lastTimestamp = 0;
    try {
      $package = $command->getPackage();

      $packageName = $package->getName();

      // check for job duplication
      if (
        $command->forceExecution() === false &&
        $lastPackage === $packageName &&
        time() - $lastTimestamp < 10
      ) {
        $this->logger->debug(
          'Update dependency status handler: Skipping duplicated job',
          [
            'package'       => $packageName,
            'lastTimestamp' => (new DateTimeImmutable)->setTimestamp($lastTimestamp)->format(DateTimeInterface::ATOM)
          ]
        );

        // shoud just accept so that it doesn't show as churn?
        return HandlerResultEnum::Reject;
      }

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

      $dependencyCol = $this->dependencyRepository->find(
        [
          'name' => $packageName
        ]
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

        if ($dependency->isDirty()) {
          $dependency = $this->dependencyRepository->update($dependency);
          $this->producer->sendEvent(
            new DependencyUpdatedEvent($dependency)
          );
        }
      }

      // update deduplication guards
      $lastPackage   = $packageName;
      $lastTimestamp = ($attributes['timestamp'] ?? new DateTimeImmutable())->getTimestamp();

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
