<?php
declare(strict_types = 1);

namespace App\Application\Message\Event\Stats;

use App\Domain\Stats\Stats;
use Courier\Message\EventInterface;

abstract class AbstractStatsEvent implements EventInterface {
  protected Stats $stats;

  public function __construct(Stats $stats) {
    $this->stats = $stats;
  }

  public function getStats(): Stats {
    return $this->stats;
  }

  public function __serialize(): array {
    return [$this->stats];
  }

  public function __unserialize(array $data): void {
    $this->stats = $data[0];
  }
}
