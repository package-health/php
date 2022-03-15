<?php
declare(strict_types = 1);

namespace App\Application\Listeners;

use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use App\Domain\Package\Package;
use Composer\Semver\Semver;
use Evenement\EventEmitter;

final class PackageListener {
  private DependencyRepositoryInterface $dependencyRepository;
  private EventEmitter $eventEmitter;

  public function __construct(
    DependencyRepositoryInterface $dependencyRepository,
    EventEmitter $eventEmitter
  ) {
    $this->dependencyRepository = $dependencyRepository;
    $this->eventEmitter         = $eventEmitter;
  }

  public function onCreated(Package $package): void {
    // echo 'Package created: ', $package->getName(), PHP_EOL;
  }

  public function onUpdated(Package $package): void {
    // echo 'Package updated: ', $package->getName(), PHP_EOL;

    $latestVersion = $package->getLatestVersion();
    if ($latestVersion === '') {
      return;
    }

    // update all packages that list $package as a require/require-dev dependency
    $dependencyCol = $this->dependencyRepository->find(
      [
        'name' => $package->getName()
      ]
    );

    foreach ($dependencyCol as $dependency) {
      $dependency = $dependency->withStatus(
        Semver::satisfies($latestVersion, $dependency->getConstraint()) ?
        DependencyStatusEnum::UpToDate :
        DependencyStatusEnum::Outdated
      );

      if ($dependency->isDirty()) {
        $dependency = $this->dependencyRepository->update($dependency);
        $this->eventEmitter->emit('dependency.updated', [$dependency]);
      }
    }
  }

  public function onDeleted(Package $package): void {
    echo 'Package deleted: ', $package->getName(), PHP_EOL;
  }
}
