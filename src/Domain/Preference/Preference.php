<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Preference;

use DateTimeImmutable;
use JsonSerializable;
use ReturnTypeWillChange;

final class Preference implements JsonSerializable {
  private ?int $id;
  private string $category;
  private string $property;
  private string $value;
  private PreferenceTypeEnum $type;
  private DateTimeImmutable $createdAt;
  private ?DateTimeImmutable $updatedAt;
  private array $changes = [];

  public function __construct(
    ?int $id,
    string $category,
    string $property,
    string $value,
    PreferenceTypeEnum $type = PreferenceTypeEnum::isString,
    DateTimeImmutable $createdAt = new DateTimeImmutable(),
    DateTimeImmutable $updatedAt = null
  ) {
    $this->id        = $id;
    $this->category  = $category;
    $this->property  = $property;
    $this->value     = $value;
    $this->type      = $type;
    $this->createdAt = $createdAt;
    $this->updatedAt = $updatedAt;
  }

  public function getId(): ?int {
    return $this->id;
  }

  public function getCategory(): string {
    return $this->category;
  }

  public function getProperty(): string {
    return $this->property;
  }

  public function getValueAsString(): string {
    return $this->value;
  }

  public function withStringValue(string $value): self {
    $clone = clone $this;
    $clone->value = $value;
    $clone->changes['value'] = $value;
    $clone->type = PreferenceTypeEnum::isString;
    $clone->changes['type'] = PreferenceTypeEnum::isString;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getValueAsInteger(): int {
    return (int)$this->value;
  }

  public function withIntegerValue(int $value): self {
    $clone = clone $this;
    $clone->value = (string)$value;
    $clone->changes['value'] = $value;
    $clone->type = PreferenceTypeEnum::isInteger;
    $clone->changes['type'] = PreferenceTypeEnum::isInteger;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getValueAsFloat(): float {
    return (float)$this->value;
  }

  public function withFloatValue(float $value): self {
    $clone = clone $this;
    $clone->value = (string)$value;
    $clone->changes['value'] = $value;
    $clone->type = PreferenceTypeEnum::isFloat;
    $clone->changes['type'] = PreferenceTypeEnum::isFloat;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getValueAsBool(): bool {
    return (bool)$this->value;
  }

  public function withBoolValue(bool $value): self {
    $clone = clone $this;
    $clone->value = (string)$value;
    $clone->changes['value'] = $value;
    $clone->type = PreferenceTypeEnum::isBool;
    $clone->changes['type'] = PreferenceTypeEnum::isBool;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getType(): PreferenceTypeEnum {
    return $this->type;
  }

  public function getCreatedAt(): DateTimeImmutable {
    return $this->createdAt;
  }

  public function getUpdatedAt(): ?DateTimeImmutable {
    return $this->updatedAt;
  }

  public function isDirty(): bool {
    return count($this->changes) > 0;
  }

  /**
   * @return array<string, mixed>
   */
  public function getChanges(): array {
    return $this->changes;
  }

  /**
   * @return array{
   *   id: int|null,
   *   category: string,
   *   property: string,
   *   value: string,
   *   type: string,
   *   createdAt: \DateTimeImmutable,
   *   updatedAt: \DateTimeImmutable|null
   * }
   */
  #[ReturnTypeWillChange]
  public function jsonSerialize(): array {
    return [
      'id'        => $this->id,
      'category'  => $this->category,
      'property'  => $this->property,
      'value'     => $this->value,
      'type'      => $this->type->value,
      'createdAt' => $this->createdAt,
      'updatedAt' => $this->updatedAt
    ];
  }
}
