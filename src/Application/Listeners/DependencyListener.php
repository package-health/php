<?php
declare(strict_types = 1);

namespace App\Application\Listeners;

use App\Domain\Dependency\Dependency;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use App\Domain\Version\VersionRepositoryInterface;
use App\Domain\Version\VersionStatusEnum;
use Evenement\EventEmitter;

final class DependencyListener {
  private VersionRepositoryInterface $versionRepository;
  private DependencyRepositoryInterface $dependencyRepository;
  private EventEmitter $eventEmitter;

  public function __construct(
    VersionRepositoryInterface $versionRepository,
    DependencyRepositoryInterface $dependencyRepository,
    EventEmitter $eventEmitter
  ) {
    $this->versionRepository    = $versionRepository;
    $this->dependencyRepository = $dependencyRepository;
    $this->eventEmitter         = $eventEmitter;
  }

  public function onCreated(Dependency $dependency): void {
    // echo 'Dependency created: ', $dependency->getId(), PHP_EOL;
  }

  public function onUpdated(Dependency $dependency): void {
    // echo 'Dependency updated: ', $dependency->getId(), PHP_EOL;

    // update the version that require $dependency (status only)
    $version = $this->versionRepository->get($dependency->getVersionId());
    $reqDeps = $this->dependencyRepository->find(
      [
        'version_id'  => $dependency->getVersionId(),
        'development' => false
      ]
    );

    $statuses = array_map(
      function (Dependency $dependency): DependencyStatusEnum {
        return $dependency->getStatus();
      },
      $reqDeps
    );

    $versionStatus = match (true) {
      in_array(DependencyStatusEnum::Insecure, $statuses) => VersionStatusEnum::Insecure,
      in_array(DependencyStatusEnum::MaybeInsecure, $statuses) => VersionStatusEnum::MaybeInsecure,
      in_array(DependencyStatusEnum::Outdated, $statuses) => VersionStatusEnum::Outdated,
      in_array(DependencyStatusEnum::Unknown, $statuses) => VersionStatusEnum::Unknown,
      in_array(DependencyStatusEnum::UpToDate, $statuses) => VersionStatusEnum::UpToDate,
      empty($statuses) => VersionStatusEnum::NoDeps,
      default => $version->getStatus()
    };

    $version = $version->withStatus($versionStatus);
    if ($version->isDirty()) {
      $version = $this->versionRepository->update($version);
      $this->eventEmitter->emit('version.updated', [$version]);
    }
  }

  public function onDeleted(Dependency $dependency): void {
    echo 'Dependency deleted: ', $dependency->getId(), PHP_EOL;
  }
}
