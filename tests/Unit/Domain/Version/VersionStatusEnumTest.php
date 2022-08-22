<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Test\Unit\Domain\Version;

use PackageHealth\PHP\Domain\Version\VersionStatusEnum;
use PHPUnit\Framework\TestCase;

final class VersionStatusEnumTest extends TestCase {
  public function testUnknownValue(): void {
    $this->assertSame('unknown', VersionStatusEnum::Unknown->value);
    $this->assertSame('', VersionStatusEnum::Unknown->getColor());
  }

  public function testOutdatedValue(): void {
    $this->assertSame('outdated', VersionStatusEnum::Outdated->value);
    $this->assertSame('is-warning', VersionStatusEnum::Outdated->getColor());
  }

  public function testInsecureValue(): void {
    $this->assertSame('insecure', VersionStatusEnum::Insecure->value);
    $this->assertSame('is-danger', VersionStatusEnum::Insecure->getColor());
  }

  public function testMaybeInsecureValue(): void {
    $this->assertSame('maybe insecure', VersionStatusEnum::MaybeInsecure->value);
    $this->assertSame('is-success', VersionStatusEnum::MaybeInsecure->getColor());
  }

  public function testUpToDateValue(): void {
    $this->assertSame('up to date', VersionStatusEnum::UpToDate->value);
    $this->assertSame('is-success', VersionStatusEnum::UpToDate->getColor());
  }

  public function testNoDepsValue(): void {
    $this->assertSame('no deps', VersionStatusEnum::NoDeps->value);
    $this->assertSame('is-info', VersionStatusEnum::NoDeps->getColor());
  }
}
