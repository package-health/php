<?php
declare(strict_types = 1);

namespace App\Application\Listeners;

use App\Domain\Stats\Stats;

final class StatsListener {
  public function __construct() {}

  public function onCreated(Stats $stats): void {
    // echo 'Stats created: ', $stats->getPackageName(), PHP_EOL;
  }

  public function onUpdated(Stats $stats): void {
    // echo 'Stats updated: ', $stats->getPackageName(), PHP_EOL;
  }

  public function onDeleted(Stats $stats): void {
    echo 'Stats deleted: ', $stats->getPackageName(), PHP_EOL;
  }
}
