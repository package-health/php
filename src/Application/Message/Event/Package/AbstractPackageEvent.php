<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Message\Event\Package;

use Courier\Message\EventInterface;
use PackageHealth\PHP\Domain\Package\Package;

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
