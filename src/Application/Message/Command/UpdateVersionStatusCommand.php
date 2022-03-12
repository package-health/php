<?php
declare(strict_types = 1);

namespace App\Application\Message\Command;

use App\Domain\Dependency\Dependency;
use Courier\Message\CommandInterface;

final class UpdateVersionStatusCommand implements CommandInterface {
  private Dependency $dependency;

  public function __construct(Dependency $dependency) {
    $this->dependency = $dependency;
  }

  public function getDependency(): Dependency {
    return $this->dependency;
  }

  public function __serialize(): array {
    return [$this->dependency];
  }

  public function __unserialize(array $data): void {
    $this->dependency = $data[0];
  }
}
