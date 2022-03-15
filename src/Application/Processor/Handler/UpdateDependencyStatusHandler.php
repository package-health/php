<?php
declare(strict_types = 1);

namespace App\Application\Processor\Handler;

use App\Application\Message\Event\Dependency\DependencyUpdatedEvent;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use Composer\Semver\Semver;
use Courier\Client\Producer;
use Courier\Message\CommandInterface;
use Courier\Processor\Handler\HandlerResultEnum;
use Courier\Processor\Handler\InvokeHandlerInterface;

class UpdateDependencyStatusHandler implements InvokeHandlerInterface {
  private DependencyRepositoryInterface $dependencyRepository;
  private Producer $producer;

  public function __construct(
    DependencyRepositoryInterface $dependencyRepository,
    Producer $producer
  ) {
    $this->dependencyRepository = $dependencyRepository;
    $this->producer             = $producer;
  }

  /**
   * Updates all dependency references that "require" or "require-dev" $package
   */
  public function __invoke(CommandInterface $command): HandlerResultEnum {
    $package = $command->getPackage();
    if ($package->getLatestVersion() === '' || $package->getLatestVersion() === 'self.version') {
      return HandlerResultEnum::Reject;
    }

    $dependencyCol = $this->dependencyRepository->find(
      [
        'name' => $package->getName()
      ]
    );

    foreach ($dependencyCol as $dependency) {
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

    return HandlerResultEnum::Accept;
  }
}
