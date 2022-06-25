<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Message\Event\Stats;

use Courier\Message\EventInterface;
use PackageHealth\PHP\Domain\Stats\Stats;

abstract class AbstractStatsEvent implements EventInterface {
  protected Stats $stats;

  public function __construct(Stats $stats) {
    $this->stats = $stats;
  }

  public function getStats(): Stats {
    return $this->stats;
  }

  /**
   * @return array{0: \App\Domain\Stats\Stats}
   */
  public function toArray(): array {
    return [$this->stats];
  }
}
