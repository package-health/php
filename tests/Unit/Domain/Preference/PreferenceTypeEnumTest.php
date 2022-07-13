<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Test\Unit\Domain\Preference;

use PackageHealth\PHP\Domain\Preference\PreferenceTypeEnum;
use PHPUnit\Framework\TestCase;

final class PreferenceTypeEnumTest extends TestCase {
  public function testIsStringValue(): void {
    $this->assertSame('string', PreferenceTypeEnum::isString->value);
  }

  public function testIsIntegerValue(): void {
    $this->assertSame('integer', PreferenceTypeEnum::isInteger->value);
  }

  public function testIsFloatValue(): void {
    $this->assertSame('float', PreferenceTypeEnum::isFloat->value);
  }

  public function testIsBoolValue(): void {
    $this->assertSame('bool', PreferenceTypeEnum::isBool->value);
  }
}
