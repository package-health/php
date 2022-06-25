<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Preference;

use Ramsey\Collection\AbstractCollection;

/**
 * @extends \Ramsey\Collection\AbstractCollection<\PackageHealth\PHP\Domain\Preference\Preference>
 */
final class PreferenceCollection extends AbstractCollection {
  public function getType(): string {
    return Preference::class;
  }
}
