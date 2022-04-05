<?php
declare(strict_types = 1);

namespace App\Application\Message\Command;

use App\Domain\Package\Package;
use Courier\Message\CommandInterface;

final class PackageDiscoveryCommand implements CommandInterface {
  private Package $package;
  /**
   * Force command execution (ie. skips command deduplication guards)
   */
  private bool $force;

  public function __construct(Package $package, bool $force = false) {
    $this->package = $package;
    $this->force   = $force;
  }

  public function getPackage(): Package {
    return $this->package;
  }

  public function forceExecution(): bool {
    return $this->force;
  }

  /**
   * @return array{
   *   0: \App\Domain\Package\Package,
   *   1: bool
   * }
   */
  public function toArray(): array {
    return [$this->package, $this->force];
  }
}
