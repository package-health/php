<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Stats;

use Ramsey\Collection\AbstractCollection;

/**
 * @extends \Ramsey\Collection\AbstractCollection<\PackageHealth\PHP\Domain\Stats\Stats>
 */
final class StatsCollection extends AbstractCollection {
  public function getType(): string {
    return Stats::class;
  }
}
