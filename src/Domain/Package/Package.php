<?php
declare(strict_types = 1);

namespace App\Domain\Package;

use DateTimeImmutable;
use JsonSerializable;
use ReturnTypeWillChange;

final class Package implements JsonSerializable {
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
    string $name,
    string $description,
    string $latestVersion,
    string $url,
    DateTimeImmutable $createdAt = new DateTimeImmutable(),
    DateTimeImmutable $updatedAt = null
  ) {
    $this->name          = $name;
    $this->description   = $description;
    $this->latestVersion = $latestVersion;
    $this->url           = $url;
    $this->createdAt     = $createdAt;
    $this->updatedAt     = $updatedAt;

    [$this->vendor, $this->project] = explode('/', $name);
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
   *   name: string,
   *   description: string,
   *   latest_version: string,
   *   url: string,
   *   created_at: int,
   *   updated_at: int|null
   * }
   */
  #[ReturnTypeWillChange]
  public function jsonSerialize(): array {
    return [
      'name'           => $this->name,
      'description'    => $this->description,
      'latest_version' => $this->latestVersion,
      'url'            => $this->url,
      'created_at'     => $this->createdAt->getTimestamp(),
      'updated_at'     => $this->updatedAt?->getTimestamp()
    ];
  }
}
