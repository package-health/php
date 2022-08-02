<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Package;

use DateTimeImmutable;
use JsonSerializable;
use ReturnTypeWillChange;

final class Package implements JsonSerializable {
  private ?int $id;
  private string $name;
  private string $vendor;
  private string $project;
  private string $description;
  private string $latestVersion;
  private string $url;
  private DateTimeImmutable $createdAt;
  private ?DateTimeImmutable $updatedAt;
  private bool $dirty = false;

  public function __construct(
    ?int $id,
    string $name,
    string $description,
    string $latestVersion,
    string $url,
    DateTimeImmutable $createdAt = new DateTimeImmutable(),
    DateTimeImmutable $updatedAt = null
  ) {
    $this->id            = $id;
    $this->name          = $name;
    $this->description   = $description;
    $this->latestVersion = $latestVersion;
    $this->url           = $url;
    $this->createdAt     = $createdAt;
    $this->updatedAt     = $updatedAt;

    [$this->vendor, $this->project] = explode('/', $name);
  }

  public function getId(): ?int {
    return $this->id;
  }

  public function getName(): string {
    return $this->name;
  }

  public function getVendor(): string {
    return $this->vendor;
  }

  public function getProject(): string {
    return $this->project;
  }

  public function getDescription(): string {
    return $this->description;
  }

  public function withDescription(string $description): self {
    if ($this->description === $description) {
      return $this;
    }

    $clone = clone $this;
    $clone->description = $description;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getLatestVersion(): string {
    return $this->latestVersion;
  }

  public function withLatestVersion(string $latestVersion): self {
    if ($this->latestVersion === $latestVersion) {
      return $this;
    }

    $clone = clone $this;
    $clone->latestVersion = $latestVersion;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getUrl(): string {
    return $this->url;
  }

  public function withUrl(string $url): self {
    if ($this->url === $url) {
      return $this;
    }

    $clone = clone $this;
    $clone->url = $url;
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
   *   name: string,
   *   description: string,
   *   latestVersion: string,
   *   url: string,
   *   createdAt: \DateTimeImmutable,
   *   updatedAt: \DateTimeImmutable|null
   * }
   */
  #[ReturnTypeWillChange]
  public function jsonSerialize(): array {
    return [
      'id'            => $this->id,
      'name'          => $this->name,
      'description'   => $this->description,
      'latestVersion' => $this->latestVersion,
      'url'           => $this->url,
      'createdAt'     => $this->createdAt,
      'updatedAt'     => $this->updatedAt
    ];
  }
}
