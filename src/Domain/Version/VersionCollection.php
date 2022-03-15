<?php
declare(strict_types = 1);

namespace App\Domain\Version;

use Ramsey\Collection\AbstractCollection;

final class VersionCollection extends AbstractCollection {
  public function getType(): string {
    return Version::class;
  }
}
