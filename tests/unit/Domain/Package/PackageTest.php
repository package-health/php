<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Test\Unit\Domain\Package;

use DateTimeImmutable;
use PackageHealth\PHP\Domain\Package\Package;
use PHPUnit\Framework\TestCase;

final class PackageTest extends TestCase {
  private array $packageAttributes;
  private Package $package;
  private DateTimeImmutable $createdAt;

  public function setUp(): void {
    $this->packageAttributes = [
      'name'          => 'vendor/project',
      'description'   => 'An awesome package description',
      'latestVersion' => '1.0',
      'url'           => 'http://'
    ];

    $this->package = new Package(...$this->packageAttributes);

    $this->createdAt = $this->package->getCreatedAt();
  }

  public function testName(): void {
    $this->assertSame('vendor/project', $this->package->getName());
  }

  public function testVendor(): void {
    $this->assertSame('vendor', $this->package->getVendor());
  }

  public function testProject(): void {
    $this->assertSame('project', $this->package->getProject());
  }

  public function testDescription(): void {
    $this->assertSame('An awesome package description', $this->package->getDescription());
  }

  public function testDescriptionHasBeenUpdated(): void {
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getCreatedAt());
    $this->assertFalse($this->package->isDirty());
    $this->assertNull($this->package->getUpdatedAt());

    $this->package = $this->package->withDescription('Even awesomer description');

    $this->assertTrue($this->package->isDirty());
    $this->assertSame('Even awesomer description', $this->package->getDescription());
    $this->assertSame($this->createdAt, $this->package->getCreatedAt());
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getUpdatedAt());
  }

  public function testDescriptionDoesNotUpdateWhenItsTheSame(): void {
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getCreatedAt());
    $this->assertFalse($this->package->isDirty());
    $this->assertNull($this->package->getUpdatedAt());

    $this->package = $this->package->withDescription('An awesome package description');

    $this->assertFalse($this->package->isDirty());
    $this->assertSame('An awesome package description', $this->package->getDescription());
    $this->assertSame($this->createdAt, $this->package->getCreatedAt());
    $this->assertNull($this->package->getUpdatedAt());
  }

  public function testLatestVersion(): void {
    $this->assertSame('1.0', $this->package->getLatestVersion());
  }

  public function testLatestVersionHasBeenUpdated(): void {
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getCreatedAt());
    $this->assertFalse($this->package->isDirty());
    $this->assertNull($this->package->getUpdatedAt());

    $this->package = $this->package->withLatestVersion('1.0.1');

    $this->assertTrue($this->package->isDirty());
    $this->assertSame('1.0.1', $this->package->getLatestVersion());
    $this->assertSame($this->createdAt, $this->package->getCreatedAt());
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getUpdatedAt());
  }

  public function testLatestVersionDoesNotUpdateWhenItsTheSame(): void {
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getCreatedAt());
    $this->assertFalse($this->package->isDirty());
    $this->assertNull($this->package->getUpdatedAt());

    $this->package = $this->package->withLatestVersion('1.0');

    $this->assertFalse($this->package->isDirty());
    $this->assertSame('1.0', $this->package->getLatestVersion());
    $this->assertSame($this->createdAt, $this->package->getCreatedAt());
    $this->assertNull($this->package->getUpdatedAt());
  }

  public function testUrl(): void {
    $this->assertSame('http://', $this->package->getUrl());
  }

  public function testUrlHasBeenUpdated(): void {
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getCreatedAt());
    $this->assertFalse($this->package->isDirty());
    $this->assertNull($this->package->getUpdatedAt());

    $this->package = $this->package->withUrl('https://');

    $this->assertTrue($this->package->isDirty());
    $this->assertSame('https://', $this->package->getUrl());
    $this->assertSame($this->createdAt, $this->package->getCreatedAt());
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getUpdatedAt());
  }

  public function testUrlDoesNotUpdateWhenItsTheSame(): void {
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getCreatedAt());
    $this->assertFalse($this->package->isDirty());
    $this->assertNull($this->package->getUpdatedAt());

    $this->package = $this->package->withUrl('http://');

    $this->assertFalse($this->package->isDirty());
    $this->assertSame('http://', $this->package->getUrl());
    $this->assertSame($this->createdAt, $this->package->getCreatedAt());
    $this->assertNull($this->package->getUpdatedAt());
  }

  public function testJsonSerializeWhenPackageWasNotUpdated() {
    $packageWasNotUpdated = array_merge(
      $this->packageAttributes,
      [
        'createdAt' => $this->createdAt,
        'updatedAt' => null
      ]
    );

    $this->assertIsArray($this->package->jsonSerialize());
    $this->assertSame($packageWasNotUpdated, $this->package->jsonSerialize());
  }

  public function testJsonSerializeWhenPackageWasUpdated(): void {
    $this->package = $this->package->withLatestVersion('1.0.1');

    $packageWasUpdated = array_merge(
      $this->packageAttributes,
      [
        'latestVersion' => '1.0.1',
        'createdAt' => $this->createdAt,
        'updatedAt' => $this->package->getUpdatedAt()
      ]
    );

    $this->assertIsArray($this->package->jsonSerialize());
    $this->assertSame($packageWasUpdated, $this->package->jsonSerialize());
  }
}
