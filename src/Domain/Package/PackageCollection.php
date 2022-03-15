<?php
declare(strict_types = 1);

namespace App\Domain\Package;

use Ramsey\Collection\AbstractCollection;

final class PackageCollection extends AbstractCollection {
  public function getType(): string {
    return Package::class;
  }
}
