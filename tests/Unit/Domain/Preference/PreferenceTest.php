<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Test\Unit\Domain\Preference;

use DateTimeImmutable;
use PackageHealth\PHP\Domain\Preference\{Preference, PreferenceTypeEnum};
use PHPUnit\Framework\TestCase;

final class PreferenceTest extends TestCase {
  private array $preferenceAttributes;
  private Preference $preference;
  private DateTimeImmutable $createdAt;

  public function setUp(): void {
    $this->preferenceAttributes = [
      'id'       => null,
      'category' => 'Category',
      'property' => 'Property',
      'value'    => 'Value'
    ];

    $this->preference = new Preference(...$this->preferenceAttributes);

    $this->createdAt = $this->preference->getCreatedAt();
  }

  public function testIdNotGiven(): void {
    $this->assertNull($this->preference->getId());
  }

  public function testIdGiven(): void {
    $id = 1;

    $preferenceAttributes = array_merge(
      $this->preferenceAttributes,
      [
        'id' => $id
      ]
    );

    $preference = new Preference(...$preferenceAttributes);

    $this->assertSame($id, $preference->getId());
  }

  public function testCategory(): void {
    $this->assertSame('Category', $this->preference->getCategory());
  }

  public function testProperty(): void {
    $this->assertSame('Property', $this->preference->getProperty());
  }

  public function testType(): void {
    $this->assertSame(PreferenceTypeEnum::isString, $this->preference->getType());
  }

  public function testCreatedAt(): void {
    $this->assertSame($this->createdAt, $this->preference->getCreatedAt());
  }

  public function testObjectIsInItsInitialState(): void {
    $this->assertInstanceOf(DateTimeImmutable::class, $this->preference->getCreatedAt());
    $this->assertFalse($this->preference->isDirty());
    $this->assertNull($this->preference->getUpdatedAt());
  }

  /**
   * @depends testObjectIsInItsInitialState
   */
  public function testValueAsString(): array {
    $preference = $this->preference->withStringValue('Value');

    $this->assertSame('Value', $preference->getValueAsString());

    return [$preference, $this->createdAt];
  }

  /**
   * @depends testObjectIsInItsInitialState
   */
  public function testValueAsInteger(): array {
    $preference = $this->preference->withIntegerValue(1);

    $this->assertSame(1, $preference->getValueAsInteger());

    return [$preference, $this->createdAt];
  }

  /**
   * @depends testObjectIsInItsInitialState
   */
  public function testValueAsFloat(): array {
    $preference = $this->preference->withFloatValue(1.0);

    $this->assertSame(1.0, $preference->getValueAsFloat());

    return [$preference, $this->createdAt];
  }

  /**
   * @depends testObjectIsInItsInitialState
   */
  public function testValueAsBool(): array {
    $preference = $this->preference->withBoolValue(true);

    $this->assertSame(true, $preference->getValueAsBool());

    return [$preference, $this->createdAt];
  }

  /**
   * @depends testValueAsString
   * @depends testValueAsInteger
   * @depends testValueAsFloat
   * @depends testValueAsBool
   */
  public function testAsString(array $testData): void {
    [$preference,] = $testData;

    $this->assertIsString($preference->getValueAsString());
  }

  /**
   * @depends testValueAsString
   * @depends testValueAsInteger
   * @depends testValueAsFloat
   * @depends testValueAsBool
   */
  public function testAsInteger(array $testData): void {
    [$preference,] = $testData;

    $this->assertIsInt($preference->getValueAsInteger());
  }

  /**
   * @depends testValueAsString
   * @depends testValueAsInteger
   * @depends testValueAsFloat
   * @depends testValueAsBool
   */
  public function testAsFloat(array $testData): void {
    [$preference,] = $testData;

    $this->assertIsFloat($preference->getValueAsFloat());
  }

  /**
   * @depends testValueAsString
   * @depends testValueAsInteger
   * @depends testValueAsFloat
   * @depends testValueAsBool
   */
  public function testAsBool(array $test): void {
    [$preference,] = $test;

    $this->assertIsBool($preference->getValueAsBool());
  }

  /**
   * @depends testValueAsString
   * @depends testValueAsInteger
   * @depends testValueAsFloat
   * @depends testValueAsBool
   */
  public function testObjectIsDirty(array $testData): void {
    [$preference, $createdAt] = $testData;

    $this->assertTrue($preference->isDirty());
    $this->assertSame($createdAt, $preference->getCreatedAt());
    $this->assertInstanceOf(DateTimeImmutable::class, $preference->getUpdatedAt());
  }


  public function testJsonSerializeWhenPreferenceWasNotUpdated(): array {
    $preferenceAttributes = array_merge(
      $this->preferenceAttributes,
      [
        'type'      => PreferenceTypeEnum::isString->value,
        'createdAt' => $this->createdAt,
        'updatedAt' => null
      ]
    );

    $this->assertNull($this->preference->getUpdatedAt());

    return [$this->preference, $preferenceAttributes];
  }

  public function testJsonSerializeWhenPreferenceTypeWasUpdatedToString(): array {
    $preference = $this->preference->withStringValue('false');

    $preferenceAttributes = array_merge(
      $this->preferenceAttributes,
      [
        'value'     => 'false',
        'type'      => PreferenceTypeEnum::isString->value,
        'createdAt' => $this->createdAt,
        'updatedAt' => $preference->getUpdatedAt()
      ]
    );

    $this->assertInstanceOf(DateTimeImmutable::class, $preference->getUpdatedAt());

    return [$preference, $preferenceAttributes];
  }

  public function testJsonSerializeWhenPreferenceTypeWasUpdatedToInteger(): array {
    $preference = $this->preference->withIntegerValue(0);

    $preferenceAttributes = array_merge(
      $this->preferenceAttributes,
      [
        'value'     => '0',
        'type'      => PreferenceTypeEnum::isInteger->value,
        'createdAt' => $this->createdAt,
        'updatedAt' => $preference->getUpdatedAt()
      ]
    );

    $this->assertInstanceOf(DateTimeImmutable::class, $preference->getUpdatedAt());

    return [$preference, $preferenceAttributes];
  }

  public function testJsonSerializeWhenPreferenceTypeWasUpdatedToFloat(): array {
    $preference = $this->preference->withFloatValue(1.0);

    $preferenceAttributes = array_merge(
      $this->preferenceAttributes,
      [
        'value'     => '1',
        'type'      => PreferenceTypeEnum::isFloat->value,
        'createdAt' => $this->createdAt,
        'updatedAt' => $preference->getUpdatedAt()
      ]
    );

    $this->assertInstanceOf(DateTimeImmutable::class, $preference->getUpdatedAt());

    return [$preference, $preferenceAttributes];
  }

  public function testJsonSerializeWhenPreferenceTypeWasUpdatedToBool(): array {
    $preference = $this->preference->withBoolValue(false);

    $preferenceAttributes = array_merge(
      $this->preferenceAttributes,
      [
        'value'     => '',
        'type'      => PreferenceTypeEnum::isBool->value,
        'createdAt' => $this->createdAt,
        'updatedAt' => $preference->getUpdatedAt()
      ]
    );

    $this->assertInstanceOf(DateTimeImmutable::class, $preference->getUpdatedAt());

    return [$preference, $preferenceAttributes];
  }

  /**
   * @depends testJsonSerializeWhenPreferenceWasNotUpdated
   * @depends testJsonSerializeWhenPreferenceTypeWasUpdatedToString
   * @depends testJsonSerializeWhenPreferenceTypeWasUpdatedToInteger
   * @depends testJsonSerializeWhenPreferenceTypeWasUpdatedToFloat
   * @depends testJsonSerializeWhenPreferenceTypeWasUpdatedToBool
   */
  public function testObjectSerializedValuesMatch(array $testData): void {
    [$preference, $preferenceAttributes] = $testData;

    $this->assertIsArray($preference->jsonSerialize());
    $this->assertSame($preferenceAttributes, $preference->jsonSerialize());
  }
}
