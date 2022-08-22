<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Test\Unit\Domain\Version;

use DateTimeImmutable;
use PackageHealth\PHP\Domain\Version\Version;
use PackageHealth\PHP\Domain\Version\VersionStatusEnum;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase {
  private array $attributes;
  private Version $version;

  public function setUp(): void {
    $this->attributes = [
      'id'         => null,
      'packageId'  => 10,
      'number'     => '1.0.1',
      'normalized' => '1.0.1.0',
      'release'    => true,
      'status'     => VersionStatusEnum::UpToDate
    ];

    $this->version = new Version(...$this->attributes);

    $this->attributes['createdAt'] = $this->version->getCreatedAt();
    $this->attributes['updatedAt'] = null;
  }

  public function testAttributes(): void {
    $this->assertNull($this->version->getId());
    $this->assertSame('1.0.1', $this->version->getNumber());
    $this->assertSame('1.0.1.0', $this->version->getNormalized());
    $this->assertSame(10, $this->version->getPackageId());
    $this->assertTrue($this->version->isRelease());
    $this->assertSame(VersionStatusEnum::UpToDate, $this->version->getStatus());
    $this->assertTrue($this->version->isStable());
    $this->assertFalse($this->version->isDirty());
    $this->assertEmpty($this->version->getChanges());
  }

  public function testDefaultAttributes(): void {
    $version = new Version(
      null,
      0,
      '',
      ''
    );

    $this->assertFalse($version->isRelease());
    $this->assertSame(VersionStatusEnum::Unknown, $version->getStatus());
  }

  public function testNumberHasBeenUpdated(): void {
    $version = $this->version->withNumber('2.0');

    $this->assertSame('2.0', $version->getNumber());
    $this->assertTrue($version->isDirty());
    $this->assertSame(['number' => '2.0'], $version->getChanges());
    $this->assertInstanceOf(DateTimeImmutable::class, $version->getUpdatedAt());
  }

  public function testNumberDoesNotUpdateWhenItsTheSame(): void {
    $version = $this->version->withNumber('1.0.1');

    $this->assertSame($version, $this->version);
  }

  public function testNormalizedHasBeenUpdated(): void {
    $version = $this->version->withNormalized('2.0.0.0');

    $this->assertSame('2.0.0.0', $version->getNormalized());
    $this->assertTrue($version->isDirty());
    $this->assertSame(['normalized' => '2.0.0.0'], $version->getChanges());
    $this->assertInstanceOf(DateTimeImmutable::class, $version->getUpdatedAt());
  }

  public function testNormalizedDoesNotUpdateWhenItsTheSame(): void {
    $version = $this->version->withNormalized('1.0.1.0');

    $this->assertSame($version, $this->version);
  }

  public function testPackageIdHasBeenUpdated(): void {
    $version = $this->version->withPackageId(8);

    $this->assertSame(8, $version->getPackageId());
    $this->assertTrue($version->isDirty());
    $this->assertSame(['packageId' => 8], $version->getChanges());
    $this->assertInstanceOf(DateTimeImmutable::class, $version->getUpdatedAt());
  }

  public function testPackageIdDoesNotUpdateWhenItsTheSame(): void {
    $version = $this->version->withPackageId(10);

    $this->assertSame($version, $this->version);
  }

  public function testReleaseHasBeenUpdated(): void {
    $version = $this->version->withRelease(false);

    $this->assertFalse($version->isRelease());
    $this->assertTrue($version->isDirty());
    $this->assertSame(['release' => false], $version->getChanges());
    $this->assertInstanceOf(DateTimeImmutable::class, $version->getUpdatedAt());
  }

  public function testReleaseDoesNotUpdateWhenItsTheSame(): void {
    $version = $this->version->withRelease(true);

    $this->assertSame($version, $this->version);
  }

  public function testStatusHasBeenUpdated(): void {
    $version = $this->version->withStatus(VersionStatusEnum::NoDeps);

    $this->assertSame(VersionStatusEnum::NoDeps, $version->getStatus());
    $this->assertTrue($version->isDirty());
    $this->assertSame(['status' => VersionStatusEnum::NoDeps], $version->getChanges());
    $this->assertInstanceOf(DateTimeImmutable::class, $version->getUpdatedAt());
  }

  public function testStatusDoesNotUpdateWhenItsTheSame(): void {
    $version = $this->version->withStatus(VersionStatusEnum::UpToDate);

    $this->assertSame($version, $this->version);
  }

  public function testMultipleUpdates(): void {
    $version = $this->version->withNumber('2.0');
    $version = $version->withNormalized('2.0.0.0');
    $version = $version->withPackageId(8);
    $version = $version->withRelease(false);
    $version = $version->withStatus(VersionStatusEnum::NoDeps);

    $updates = [
      'number'     => '2.0',
      'normalized' => '2.0.0.0',
      'packageId'  => 8,
      'release'    => false,
      'status'     => VersionStatusEnum::NoDeps
    ];

    $this->assertSame('2.0', $version->getNumber());
    $this->assertSame('2.0.0.0', $version->getNormalized());
    $this->assertSame(8, $version->getPackageId());
    $this->assertFalse($version->isRelease());
    $this->assertSame(VersionStatusEnum::NoDeps, $version->getStatus());
    $this->assertTrue($version->isDirty());
    $this->assertSame($updates, $version->getChanges());
    $this->assertInstanceOf(DateTimeImmutable::class, $version->getUpdatedAt());
  }

  public function testIsStable(): void {
    $version = new Version(null, 0, '1.0', '1.0.0.0');
    $this->assertTrue($version->isStable());

    $version = new Version(null, 0, '1.0a', '1.0.0.0-alpha');
    $this->assertFalse($version->isStable());

    $version = new Version(null, 0, '1.0b', '1.0.0.0-beta');
    $this->assertFalse($version->isStable());

    $version = new Version(null, 0, '1.0rc', '1.0.0.0-RC');
    $this->assertFalse($version->isStable());

    $version = new Version(null, 0, '1.0-dev', '1.0.0.0-dev');
    $this->assertFalse($version->isStable());
  }

  public function testObjectSerialization(): void {
    $this->assertSame($this->attributes, $this->version->jsonSerialize());
  }
}
