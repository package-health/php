<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Version;

use Ramsey\Collection\AbstractCollection;

/**
 * @extends \Ramsey\Collection\AbstractCollection<\App\Domain\Version\Version>
 */
final class VersionCollection extends AbstractCollection {
  public function getType(): string {
    return Version::class;
  }
}
