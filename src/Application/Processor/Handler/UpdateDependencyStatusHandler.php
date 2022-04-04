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

class UpdateDependencyStatusHandler implements InvokeHandlerInterface {
  private DependencyRepositoryInterface $dependencyRepository;
  private ProducerInterface $producer;

  public function __construct(
    DependencyRepositoryInterface $dependencyRepository,
    ProducerInterface $producer
  ) {
    $this->dependencyRepository = $dependencyRepository;
    $this->producer             = $producer;
  }

  /**
   * Updates all dependency references that "require" or "require-dev" $package
   */
  public function __invoke(CommandInterface $command): HandlerResultEnum {
    $package = $command->getPackage();
    $this->logger->info(
      'Update dependency status handler',
      [
        'package' => $package->getName(),
        'version' => $package->getLatestVersion()
      ]
    );

    if ($package->getLatestVersion() === '') {
      return HandlerResultEnum::Reject;
    }

    $dependencyCol = $this->dependencyRepository->find(
      [
        'name' => $package->getName()
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

    return HandlerResultEnum::Accept;
  }
}
