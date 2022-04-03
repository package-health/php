<?php
declare(strict_types = 1);

namespace App\Application\Message\Event\Dependency;

use App\Domain\Dependency\Dependency;
use Courier\Message\EventInterface;

abstract class AbstractDependencyEvent implements EventInterface {
  protected Dependency $dependency;

  public function __construct(Dependency $dependency) {
    $this->dependency = $dependency;
  }

  public function getDependency(): Dependency {
    return $this->dependency;
  }

  /**
   * @return array{0: \App\Domain\Dependency\Dependency}
   */
  public function toArray(): array {
    return [$this->dependency];
  }
}
