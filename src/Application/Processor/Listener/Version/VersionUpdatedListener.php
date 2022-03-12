<?php
declare(strict_types = 1);

namespace App\Application\Processor\Listener\Version;

use Courier\Message\EventInterface;
use Courier\Processor\Listener\InvokeListenerInterface;
use Psr\Log\LoggerInterface;

class VersionUpdatedListener implements InvokeListenerInterface {
  private LoggerInterface $logger;

  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  public function __invoke(EventInterface $event): void {
    $this->logger->debug('Version updated', [$event->getVersion()]);
  }
}
