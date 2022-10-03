<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Processor\Listener\Package;

use Courier\Client\Producer;
use Courier\Message\EventInterface;
use Courier\Processor\Listener\InvokeListenerInterface;
use PackageHealth\PHP\Application\Message\Event\Package\PackageUpdatedEvent;
use PackageHealth\PHP\Application\Message\Command\UpdateDependencyStatusCommand;
use Psr\Log\LoggerInterface;

final class PackageUpdatedListener implements InvokeListenerInterface {
  private Producer $producer;
  private LoggerInterface $logger;

  public function __construct(Producer $producer, LoggerInterface $logger) {
    $this->producer = $producer;
    $this->logger   = $logger;
  }

  public function __invoke(EventInterface $event, array $attributes = []): void {
    if (($event instanceof PackageUpdatedEvent) === false) {
      $this->logger->critical(
        sprintf(
          'Invalid event argument for PackageUpdatedListener: "%s"',
          $event::class
        )
      );

      return;
    }

    $package = $event->getPackage();
    $this->logger->debug(
      'Package updated',
      [
        'packageId' => $package->getId(),
        'name'      => $package->getName()
      ]
    );

    // ignore updates with empty latest version as they have no side effects
    if ($package->getLatestVersion() === '') {
      return;
    }

    $this->producer->sendCommand(
      new UpdateDependencyStatusCommand($package)
    );
  }
}
