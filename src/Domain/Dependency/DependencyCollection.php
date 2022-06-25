<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Dependency;

use Ramsey\Collection\AbstractCollection;

/**
 * @extends \Ramsey\Collection\AbstractCollection<\PackageHealth\PHP\Domain\Dependency\Dependency>
 */
final class DependencyCollection extends AbstractCollection {
  public function getType(): string {
    return Dependency::class;
  }
}
