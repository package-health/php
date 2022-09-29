<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Dependency;

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
  private array $changes = [];

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
    $name = trim($name);
    DependencyValidator::assertValidName($name);

    $constraint = trim($constraint);
    DependencyValidator::assertValidConstraint($constraint);

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

  public function getName(): string {
    return $this->name;
  }

  public function getConstraint(): string {
    return $this->constraint;
  }

  public function isDevelopment(): bool {
    return $this->development === true;
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
    $clone->changes['status'] = $status;
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
   *   versionId: int,
   *   name: string,
   *   constraint: string,
   *   development: bool,
   *   status: \PackageHealth\PHP\Domain\Dependency\DependencyStatusEnum,
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
