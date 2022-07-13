<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Test\Unit\Domain\Preference;

use PackageHealth\PHP\Domain\Preference\{Preference, PreferenceCollection};
use PHPUnit\Framework\TestCase;

final class PreferenceCollectionTest extends TestCase {
  public function testIsOfPreferenceType(): void {
    $preferenceCollection = new PreferenceCollection();

    $this->assertSame(Preference::class, $preferenceCollection->getType());
  }
}
