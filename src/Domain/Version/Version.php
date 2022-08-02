<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Version;

use Composer\Semver\VersionParser;
use DateTimeImmutable;
use JsonSerializable;
use ReturnTypeWillChange;

final class Version implements JsonSerializable {
  private ?int $id;
  private int $packageId;
  private string $number;
  private string $normalized;
  private bool $release;
  private VersionStatusEnum $status;
  private DateTimeImmutable $createdAt;
  private ?DateTimeImmutable $updatedAt;
  private bool $dirty = false;

  public function __construct(
    ?int $id,
    int $packageId,
    string $number,
    string $normalized,
    bool $release = false,
    VersionStatusEnum $status = VersionStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable(),
    DateTimeImmutable $updatedAt = null
  ) {
    $this->id         = $id;
    $this->packageId  = $packageId;
    $this->number     = $number;
    $this->normalized = $normalized;
    $this->release    = $release;
    $this->status     = $status;
    $this->createdAt  = $createdAt;
    $this->updatedAt  = $updatedAt;
  }

  public function getId(): ?int {
    return $this->id;
  }

  public function getPackageId(): int {
    return $this->packageId;
  }

  public function withPackageId(int $packageId): self {
    if ($this->packageId === $packageId) {
      return $this;
    }

    $clone = clone $this;
    $clone->packageId = $packageId;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getNumber(): string {
    return $this->number;
  }

  public function withNumber(string $number): self {
    if ($this->number === $number) {
      return $this;
    }

    $clone = clone $this;
    $clone->number = $number;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getNormalized(): string {
    return $this->normalized;
  }

  public function withNormalized(string $normalized): self {
    if ($this->normalized === $normalized) {
      return $this;
    }

    $clone = clone $this;
    $clone->normalized = $normalized;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function isRelease(): bool {
    return $this->release;
  }

  public function withRelease(bool $release): self {
    if ($this->release === $release) {
      return $this;
    }

    $clone = clone $this;
    $clone->release = $release;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getStatus(): VersionStatusEnum {
    return $this->status;
  }

  public function withStatus(VersionStatusEnum $status): self {
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

  public function isStable(): bool {
    return VersionParser::parseStability($this->normalized) === 'stable';
  }

  public function isDirty(): bool {
    return $this->dirty;
  }

  /**
   * @return array{
   *   id: int|null,
   *   packageId: int,
   *   number: string,
   *   release: bool,
   *   status: \PackageHealth\PHP\Domain\Version\VersionStatusEnum,
   *   createdAt: \DateTimeImmutable,
   *   updatedAt: \DateTimeImmutable|null
   * }
   */
  #[ReturnTypeWillChange]
  public function jsonSerialize(): array {
    return [
      'id'        => $this->id,
      'packageId' => $this->packageId,
      'number'    => $this->number,
      'release'   => $this->release,
      'status'    => $this->status,
      'createdAt' => $this->createdAt,
      'updatedAt' => $this->updatedAt
    ];
  }
}
