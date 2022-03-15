<?php
declare(strict_types = 1);

namespace App\Domain\Stats;

use Ramsey\Collection\AbstractCollection;

final class StatsCollection extends AbstractCollection {
  public function getType(): string {
    return Stats::class;
  }
}
