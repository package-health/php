<?php
declare(strict_types = 1);

namespace App\Application\Message\Command;

use App\Domain\Package\Package;
use Courier\Message\CommandInterface;

final class UpdateDependencyStatusCommand implements CommandInterface {
  private Package $package;
  /**
   * Force command execution (ie. skips command deduplication guards)
   */
  private bool $forceExecution;

  public function __construct(Package $package, bool $forceExecution = false) {
    $this->package        = $package;
    $this->forceExecution = $forceExecution;
  }

  public function getPackage(): Package {
    return $this->package;
  }

  public function forceExecution(): bool {
    return $this->forceExecution;
  }

  /**
   * @return array{
   *   0: \App\Domain\Package\Package,
   *   1: bool
   * }
   */
  public function toArray(): array {
    return [$this->package, $this->forceExecution];
  }
}
