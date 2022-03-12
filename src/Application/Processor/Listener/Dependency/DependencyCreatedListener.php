<?php
declare(strict_types = 1);

namespace App\Application\Processor\Listener\Dependency;

use App\Application\Message\Event\Package\PackageCreatedEvent;
use App\Domain\Package\PackageRepositoryInterface;
use Courier\Client\Producer;
use Courier\Message\EventInterface;
use Courier\Processor\Listener\InvokeListenerInterface;
use Psr\Log\LoggerInterface;

class DependencyCreatedListener implements InvokeListenerInterface {
  private PackageRepositoryInterface $packageRepository;
  private Producer $producer;
  private LoggerInterface $logger;

  public function __construct(
    PackageRepositoryInterface $packageRepository,
    Producer $producer,
    LoggerInterface $logger
  ) {
    $this->packageRepository = $packageRepository;
    $this->producer          = $producer;
    $this->logger            = $logger;
  }

  /**
   * Checks if the dependency is registered as a package, if it's not, registers it.
   */
  public function __invoke(EventInterface $event): void {
    $dependency = $event->getDependency();
    $this->logger->debug('Dependency created', [$dependency]);

    // $packageCol = $this->packageRepository->find(
    //   [
    //     'name' => $dependency->getName()
    //   ]
    // );

    // $package = $packageCol[0] ?? null;
    // if ($package === null) {
    //   $package = $this->packageRepository->create(
    //     $dependency->getName()
    //   );

    //   $this->producer->sendEvent(
    //     new PackageCreatedEvent($package)
    //   );
    // }
  }
}
