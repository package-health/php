<?php
declare(strict_types = 1);

namespace App\Application\Processor\Listener\Package;

use App\Application\Message\Command\UpdateDependencyStatusCommand;
use Courier\Client\Producer\ProducerInterface;
use Courier\Message\EventInterface;
use Courier\Processor\Listener\InvokeListenerInterface;
use Psr\Log\LoggerInterface;

final class PackageUpdatedListener implements InvokeListenerInterface {
  private ProducerInterface $producer;
  private LoggerInterface $logger;

  public function __construct(ProducerInterface $producer, LoggerInterface $logger) {
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
      ['package' => $package->getName()]
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
