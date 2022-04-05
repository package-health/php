<?php
declare(strict_types = 1);

namespace App\Application\Message\Command;

use App\Domain\Dependency\Dependency;
use Courier\Message\CommandInterface;

final class UpdateVersionStatusCommand implements CommandInterface {
  private Dependency $dependency;
  /**
   * Force command execution (ie. skips command deduplication guards)
   */
  private bool $force;

  public function __construct(Dependency $dependency, bool $force = false) {
    $this->dependency = $dependency;
    $this->force      = $force;
  }

  public function getDependency(): Dependency {
    return $this->dependency;
  }

  public function forceExecution(): bool {
    return $this->force;
  }

  /**
   * @return array{
   *   0: \App\Domain\Dependency\Dependency,
   *   1: bool
   * }
   */
  public function toArray(): array {
    return [$this->dependency, $this->force];
  }
}
