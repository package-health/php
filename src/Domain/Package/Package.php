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
  private array $changes = [];

  public function __construct(
    ?int $id,
    string $name,
    string $description,
    string $latestVersion,
    string $url,
    DateTimeImmutable $createdAt = new DateTimeImmutable(),
    DateTimeImmutable $updatedAt = null
  ) {
    $name = trim($name);
    PackageValidator::assertValid($name);

    $this->id            = $id;
    $this->name          = $name;
    $this->description   = trim($description);
    $this->latestVersion = trim($latestVersion);
    $this->url           = trim($url);
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
    $description = trim($description);
    if ($this->description === $description) {
      return $this;
    }

    $clone = clone $this;
    $clone->description = $description;
    $clone->changes['description'] = $description;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getLatestVersion(): string {
    return $this->latestVersion;
  }

  public function withLatestVersion(string $latestVersion): self {
    $latestVersion = trim($latestVersion);
    if ($this->latestVersion === $latestVersion) {
      return $this;
    }

    $clone = clone $this;
    $clone->latestVersion = $latestVersion;
    $clone->changes['latestVersion'] = $latestVersion;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getUrl(): string {
    return $this->url;
  }

  public function withUrl(string $url): self {
    $url = trim($url);
    if ($this->url === $url) {
      return $this;
    }

    $clone = clone $this;
    $clone->url = $url;
    $clone->changes['url'] = $url;
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
