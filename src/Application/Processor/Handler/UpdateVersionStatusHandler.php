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

final class UpdateVersionStatusHandler implements InvokeHandlerInterface {
  private VersionRepositoryInterface $versionRepository;
  private DependencyRepositoryInterface $dependencyRepository;
  private ProducerInterface $producer;

  public function __construct(
    VersionRepositoryInterface $versionRepository,
    DependencyRepositoryInterface $dependencyRepository,
    ProducerInterface $producer
  ) {
    $this->versionRepository    = $versionRepository;
    $this->dependencyRepository = $dependencyRepository;
    $this->producer             = $producer;
  }

  /**
   * Updates the version status that requires $dependency
   */
  public function __invoke(CommandInterface $command): HandlerResultEnum {
    $dependency = $command->getDependency();

    $this->logger->info(
      'Update version status handler',
      ['dependency' => $dependency->getName()]
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

    return HandlerResultEnum::Accept;
  }
}
