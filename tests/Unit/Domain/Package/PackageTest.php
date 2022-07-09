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

  public function testObjectIsInItsInitialState(): void {
    $this->assertInstanceOf(DateTimeImmutable::class, $this->package->getCreatedAt());
    $this->assertFalse($this->package->isDirty());
    $this->assertNull($this->package->getUpdatedAt());
  }

  /**
   * @depends testObjectIsInItsInitialState
   */
  public function testDescriptionHasBeenUpdated(): array {
    $package = $this->package->withDescription('Even awesomer description');

    $this->assertSame('Even awesomer description', $package->getDescription());

    return [$package, $this->createdAt];
  }

  /**
   * @depends testObjectIsInItsInitialState
   */
  public function testDescriptionDoesNotUpdateWhenItsTheSame(): array {
    $package = $this->package->withDescription('An awesome package description');

    $this->assertSame('An awesome package description', $package->getDescription());

    return [$package, $this->createdAt];
  }

  public function testLatestVersion(): void {
    $this->assertSame('1.0', $this->package->getLatestVersion());
  }

  /**
   * @depends testObjectIsInItsInitialState
   */
  public function testLatestVersionHasBeenUpdated(): array {
    $package = $this->package->withLatestVersion('1.0.1');

    $this->assertSame('1.0.1', $package->getLatestVersion());

    return [$package, $this->createdAt];
  }

  /**
   * @depends testObjectIsInItsInitialState
   */
  public function testLatestVersionDoesNotUpdateWhenItsTheSame(): array {
    $package = $this->package->withLatestVersion('1.0');

    $this->assertSame('1.0', $package->getLatestVersion());

    return [$package, $this->createdAt];
  }

  public function testUrl(): void {
    $this->assertSame('http://', $this->package->getUrl());
  }

  /**
   * @depends testObjectIsInItsInitialState
   */
  public function testUrlHasBeenUpdated(): array {
    $package = $this->package->withUrl('https://');

    $this->assertSame('https://', $package->getUrl());

    return [$package, $this->createdAt];
  }

  /**
   * @depends testObjectIsInItsInitialState
   */
  public function testUrlDoesNotUpdateWhenItsTheSame(): array {
    $package = $this->package->withUrl('http://');

    $this->assertSame('http://', $package->getUrl());

    return [$package, $this->createdAt];
  }

  /**
   * @depends testDescriptionHasBeenUpdated
   * @depends testLatestVersionHasBeenUpdated
   * @depends testUrlHasBeenUpdated
   */
  public function testIsDirty(array $testData): void {
    [$package, $createdAt] = $testData;

    $this->assertTrue($package->isDirty());
  }

  /**
   * @depends testDescriptionHasBeenUpdated
   * @depends testLatestVersionHasBeenUpdated
   * @depends testUrlHasBeenUpdated
   */
  public function testSameCreatedAt(array $testData): void {
    [$package, $createdAt] = $testData;

    $this->assertSame($createdAt, $package->getCreatedAt());
  }

  /**
   * @depends testDescriptionHasBeenUpdated
   * @depends testLatestVersionHasBeenUpdated
   * @depends testUrlHasBeenUpdated
   */
  public function testUpdatedAt(array $testData): void {
    [$package, $createdAt] = $testData;

    $this->assertInstanceOf(DateTimeImmutable::class, $package->getUpdatedAt());
  }

  /**
   * @depends testDescriptionDoesNotUpdateWhenItsTheSame
   * @depends testLatestVersionDoesNotUpdateWhenItsTheSame
   * @depends testUrlDoesNotUpdateWhenItsTheSame
   */
  public function testUpdatedAtIsNull(array $testData): void {
    [$package, $createdAt] = $testData;

    $this->assertNull($package->getUpdatedAt());
  }

  public function testJsonSerializeWhenPackageWasNotUpdated() {
    $packageAttributes = array_merge(
      $this->packageAttributes,
      [
        'createdAt' => $this->createdAt,
        'updatedAt' => null
      ]
    );

    $this->assertNull($this->package->getUpdatedAt());

    return [$this->package, $packageAttributes];
  }

  public function testJsonSerializeWhenPackageWasUpdated(): array {
    $package = $this->package->withLatestVersion('1.0.1');

    $packageAttributes = array_merge(
      $this->packageAttributes,
      [
        'latestVersion' => '1.0.1',
        'createdAt' => $this->createdAt,
        'updatedAt' => $package->getUpdatedAt()
      ]
    );

    $this->assertInstanceOf(DateTimeImmutable::class, $package->getUpdatedAt());

    return [$package, $packageAttributes];
  }

  /**
   * @depends testJsonSerializeWhenPackageWasNotUpdated
   * @depends testJsonSerializeWhenPackageWasUpdated
   */
  public function testObjectSerializedValuesMatch(array $testData): void {
    [$preference, $preferenceAttributes] = $testData;

    $this->assertIsArray($preference->jsonSerialize());
    $this->assertSame($preferenceAttributes, $preference->jsonSerialize());
  }
}
