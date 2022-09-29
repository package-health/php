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
  private array $changes = [];

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
    $number = trim($number);
    VersionValidator::assertValidNumber($number);

    $normalized = trim($normalized);
    VersionValidator::assertValidNormalizedNumber($normalized);

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

  public function getNumber(): string {
    return $this->number;
  }

  public function getNormalized(): string {
    return $this->normalized;
  }

  public function isRelease(): bool {
    return $this->release;
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

  public function isStable(): bool {
    return VersionParser::parseStability($this->normalized) === 'stable';
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
   *   packageId: int,
   *   number: string,
   *   normalized: string,
   *   release: bool,
   *   status: \PackageHealth\PHP\Domain\Version\VersionStatusEnum,
   *   createdAt: \DateTimeImmutable,
   *   updatedAt: \DateTimeImmutable|null
   * }
   */
  #[ReturnTypeWillChange]
  public function jsonSerialize(): array {
    return [
      'id'         => $this->id,
      'packageId'  => $this->packageId,
      'number'     => $this->number,
      'normalized' => $this->normalized,
      'release'    => $this->release,
      'status'     => $this->status,
      'createdAt'  => $this->createdAt,
      'updatedAt'  => $this->updatedAt
    ];
  }
}
