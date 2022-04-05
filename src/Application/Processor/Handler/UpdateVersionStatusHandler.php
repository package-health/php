<?php
declare(strict_types = 1);

namespace App\Application\Processor\Handler;

use App\Application\Message\Event\Version\VersionUpdatedEvent;
use App\Domain\Dependency\Dependency;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use App\Domain\Version\VersionRepositoryInterface;
use App\Domain\Version\VersionStatusEnum;
use Courier\Client\Producer\ProducerInterface;
use Courier\Message\CommandInterface;
use Courier\Processor\Handler\HandlerResultEnum;
use Courier\Processor\Handler\InvokeHandlerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;

final class UpdateVersionStatusHandler implements InvokeHandlerInterface {
  private VersionRepositoryInterface $versionRepository;
  private DependencyRepositoryInterface $dependencyRepository;
  private ProducerInterface $producer;
  private LoggerInterface $logger;

  public function __construct(
    VersionRepositoryInterface $versionRepository,
    DependencyRepositoryInterface $dependencyRepository,
    ProducerInterface $producer,
    LoggerInterface $logger
  ) {
    $this->versionRepository    = $versionRepository;
    $this->dependencyRepository = $dependencyRepository;
    $this->producer             = $producer;
    $this->logger               = $logger;
  }

  /**
   * Updates the version status that requires $dependency
   */
  public function __invoke(CommandInterface $command): HandlerResultEnum {
    static $lastDependency = '';
    static $lastTimestamp = 0;
    try {

      $dependency = $command->getDependency();

      $dependencyName = $dependency->getName();

      // check for job duplication
      if (
        $command->forceExecution() === false &&
        $lastDependency === $dependencyName &&
        time() - $lastTimestamp < 10
      ) {
        $this->logger->info(
          'Update version status handler: Skipping duplicated job',
          [
            'dependency'    => $dependencyName,
            'lastTimestamp' => (new DateTimeImmutable)->setTimestamp($lastTimestamp)->format(DateTimeInterface::ATOM)
          ]
        );

        // shoud just accept so that it doesn't show as churn?
        return HandlerResultEnum::Reject;
      }

      $this->logger->info(
        'Update version status handler',
        ['dependency' => $dependencyName]
      );

      $version = $this->versionRepository->get($dependency->getVersionId());
      $reqDeps = $this->dependencyRepository->find(
        [
          'version_id'  => $dependency->getVersionId(),
          'development' => false
        ]
      );

      $statuses = $reqDeps->map(
        static function (Dependency $dependency): DependencyStatusEnum {
          return $dependency->getStatus();
        }
      );

      $version = $version->withStatus(
        match (true) {
          $statuses->contains(DependencyStatusEnum::Insecure)      => VersionStatusEnum::Insecure,
          $statuses->contains(DependencyStatusEnum::MaybeInsecure) => VersionStatusEnum::MaybeInsecure,
          $statuses->contains(DependencyStatusEnum::Outdated)      => VersionStatusEnum::Outdated,
          $statuses->contains(DependencyStatusEnum::Unknown)       => VersionStatusEnum::Unknown,
          $statuses->contains(DependencyStatusEnum::UpToDate)      => VersionStatusEnum::UpToDate,
          $statuses->isEmpty()                                     => VersionStatusEnum::NoDeps,
          default                                                  => $version->getStatus()
        }
      );

      if ($version->isDirty()) {
        $version = $this->versionRepository->update($version);

        $this->producer->sendEvent(
          new VersionUpdatedEvent($version)
        );
      }

      // update deduplication guards
      $lastDependency = $dependencyName;
      $lastTimestamp = time();

      return HandlerResultEnum::Accept;
    } catch (Exception $exception) {
      $this->logger->error(
        $exception->getMessage(),
        [
          'dependency' => $dependencyName,
          'exception' => [
            'file'  => $exception->getFile(),
            'line'  => $exception->getLine(),
            'trace' => $exception->getTrace()
          ]
        ]
      );

      return HandlerResultEnum::Requeue;
    }
  }
}
