<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Stats;

use PackageHealth\PHP\Domain\Exception\DomainRecordNotFoundException;

class StatsNotFoundException extends DomainRecordNotFoundException {
  /**
   * @var string
   */
  protected $message = 'Stats not found';
}
