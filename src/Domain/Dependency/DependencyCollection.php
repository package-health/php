<?php
declare(strict_types = 1);

namespace App\Domain\Dependency;

use Ramsey\Collection\AbstractCollection;

final class DependencyCollection extends AbstractCollection {
  public function getType(): string {
    return Dependency::class;
  }
}
