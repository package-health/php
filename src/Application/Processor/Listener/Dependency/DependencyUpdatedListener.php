<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Processor\Listener\Dependency;

use Courier\Client\Producer\ProducerInterface;
use Courier\Message\EventInterface;
use Courier\Processor\Listener\InvokeListenerInterface;
use PackageHealth\PHP\Application\Message\Event\Dependency\DependencyUpdatedEvent;
use PackageHealth\PHP\Application\Message\Command\UpdateVersionStatusCommand;
use Psr\Log\LoggerInterface;

final class DependencyUpdatedListener implements InvokeListenerInterface {
  private ProducerInterface $producer;
  private LoggerInterface $logger;

  public function __construct(ProducerInterface $producer, LoggerInterface $logger) {
    $this->producer = $producer;
    $this->logger   = $logger;
  }

  /**
   * update the version's status that requires $dependency
   */
  public function __invoke(EventInterface $event, array $attributes = []): void {
    if (($event instanceof DependencyUpdatedEvent) === false) {
      $this->logger->critical(
        sprintf(
          'Invalid event argument for DependencyUpdateListener: "%s"',
          $event::class
        )
      );

      return;
    }

    $dependency = $event->getDependency();
    $this->logger->debug(
      'Dependency updated',
      ['dependency' => $dependency->getName()]
    );

    $this->producer->sendCommand(
      new UpdateVersionStatusCommand($dependency)
    );
  }
}
