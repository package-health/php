<?php
declare(strict_types = 1);

namespace App\Application\Message\Event\Package;

use App\Domain\Package\Package;
use Courier\Message\EventInterface;

abstract class AbstractPackageEvent implements EventInterface {
  protected Package $package;

  public function __construct(Package $package) {
    $this->package = $package;
  }

  public function getPackage(): Package {
    return $this->package;
  }

  /**
   * @return array{0: \App\Domain\Package\Package}
   */
  public function toArray(): array {
    return [$this->package];
  }
}
