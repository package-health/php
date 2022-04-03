<?php
declare(strict_types = 1);

namespace App\Application\Message\Command;

use App\Domain\Package\Package;
use Courier\Message\CommandInterface;

final class PackageDiscoveryCommand implements CommandInterface {
  private Package $package;

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
