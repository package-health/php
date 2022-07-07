<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Test\Unit\Domain\Package;

use DateTimeImmutable;
use PackageHealth\PHP\Domain\Package\Package;
use PHPUnit\Framework\TestCase;

final class PackageTest extends TestCase {
  private Package $package;
  private DateTimeImmutable $createdAt;

  public function setUp(): void {
    $this->package = new Package(
      name:           'vendor/project',
      description:    'An awesome package description',
      latestVersion:  '1.0',
      url:            'http://'
    );

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

  public function testLatestVersion(): void {
    $this->assertSame('1.0', $this->package->getLatestVersion());
  }

  public function testLatestversionHasBeenUpdated(): void {
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getCreatedAt());
    $this->assertFalse($this->package->isDirty());
    $this->assertNull($this->package->getUpdatedAt());

    $this->package = $this->package->withLatestVersion('1.0.1');

    $this->assertTrue($this->package->isDirty());
    $this->assertSame('1.0.1', $this->package->getLatestVersion());
    $this->assertSame($this->createdAt, $this->package->getCreatedAt());
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getUpdatedAt());
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
}
