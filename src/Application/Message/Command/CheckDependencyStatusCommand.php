<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Message\Command;

use Courier\Message\CommandInterface;
use PackageHealth\PHP\Domain\Dependency\Dependency;

final class CheckDependencyStatusCommand implements CommandInterface {
  private Dependency $dependency;
  /**
   * Force command execution (ie. skips command deduplication guards)
   */
  private bool $forceExecution;

  public function __construct(Dependency $dependency, bool $forceExecution = false) {
    $this->dependency     = $dependency;
    $this->forceExecution = $forceExecution;
  }

  public function getDependency(): Dependency {
    return $this->dependency;
  }

  public function forceExecution(): bool {
    return $this->forceExecution;
  }

  /**
   * @return array{
   *   0: \PackageHealth\PHP\Domain\Dependency\Dependency,
   *   1: bool
   * }
   */
  public function toArray(): array {
    return [$this->dependency, $this->forceExecution];
  }
}
