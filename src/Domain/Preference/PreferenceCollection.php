<?php
declare(strict_types = 1);

namespace App\Domain\Preference;

use Ramsey\Collection\AbstractCollection;

/**
 * @extends \Ramsey\Collection\AbstractCollection<\App\Domain\Preference\Preference>
 */
final class PreferenceCollection extends AbstractCollection {
  public function getType(): string {
    return Preference::class;
  }
}