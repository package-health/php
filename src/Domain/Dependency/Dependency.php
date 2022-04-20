<?php
declare(strict_types = 1);

namespace App\Domain\Dependency;

use DateTimeImmutable;
use JsonSerializable;
use ReturnTypeWillChange;

final class Dependency implements JsonSerializable {
  private ?int $id;
  private int $versionId;
  private string $name;
  private string $constraint;
  private bool $development;
  private DependencyStatusEnum $status;
  private DateTimeImmutable $createdAt;
  private ?DateTimeImmutable $updatedAt;
  private bool $dirty = false;

  public function __construct(
    ?int $id,
    int $versionId,
    string $name,
    string $constraint,
    bool $development = false,
    DependencyStatusEnum $status = DependencyStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable(),
    DateTimeImmutable $updatedAt = null
  ) {
    $this->id          = $id;
    $this->versionId   = $versionId;
    $this->name        = $name;
    $this->constraint  = $constraint;
    $this->development = $development;
    $this->status      = $status;
    $this->createdAt   = $createdAt;
    $this->updatedAt   = $updatedAt;
  }

  public function getId(): ?int {
    return $this->id;
  }

  public function getVersionId(): int {
    return $this->versionId;
  }

  public function withVersionId(int $versionId): self {
    if ($this->versionId === $versionId) {
      return $this;
    }

    $clone = clone $this;
    $clone->versionId = $versionId;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getName(): string {
    return $this->name;
  }

  public function withName(string $name): self {
    if ($this->name === $name) {
      return $this;
    }

    $clone = clone $this;
    $clone->name = $name;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getConstraint(): string {
    return $this->constraint;
  }

  public function withConstraint(string $constraint): self {
    if ($this->constraint === $constraint) {
      return $this;
    }

    $clone = clone $this;
    $clone->constraint = $constraint;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function isDevelopment(): bool {
    return $this->development;
  }

  public function getStatus(): DependencyStatusEnum {
    return $this->status;
  }

  public function withStatus(DependencyStatusEnum $status): self {
    if ($this->status === $status) {
      return $this;
    }

    $clone = clone $this;
    $clone->status = $status;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getCreatedAt(): DateTimeImmutable {
    return $this->createdAt;
  }

  public function getUpdatedAt(): ?DateTimeImmutable {
    return $this->updatedAt;
  }

  public function isDirty(): bool {
    return $this->dirty;
  }

  /**
   * @return array{
   *   id: int|null,
   *   versionId: int,
   *   name: string,
   *   constraint: string,
   *   development: bool,
   *   status: \App\Domain\Dependency\DependencyStatusEnum,
   *   createdAt: \DateTimeImmutable,
   *   updatedAt: \DateTimeImmutable|null
   * }
   */
  #[ReturnTypeWillChange]
  public function jsonSerialize(): array {
    return [
      'id'          => $this->id,
      'versionId'   => $this->versionId,
      'name'        => $this->name,
      'constraint'  => $this->constraint,
      'development' => $this->development,
      'status'      => $this->status,
      'createdAt'   => $this->createdAt,
      'updatedAt'   => $this->updatedAt
    ];
  }
}
