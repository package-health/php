<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Package;

use Ramsey\Collection\AbstractCollection;

/**
 * @extends \Ramsey\Collection\AbstractCollection<\PackageHealth\PHP\Domain\Package\Package>
 */
final class PackageCollection extends AbstractCollection {
  public function getType(): string {
    return Package::class;
  }
}
