<?php
declare(strict_types = 1);

namespace App\Domain\Stats;

use DateTimeImmutable;
use JsonSerializable;
use ReturnTypeWillChange;

final class Stats implements JsonSerializable {
  private string $packageName;
  private int $githubStars;
  private int $githubWatchers;
  private int $githubForks;
  private int $dependents;
  private int $suggesters;
  private int $favers;
  private int $totalDownloads;
  private int $monthlyDownloads;
  private int $dailyDownloads;
  private DateTimeImmutable $createdAt;
  private ?DateTimeImmutable $updatedAt;
  private bool $dirty = false;

  public function __construct(
    string $packageName,
    int $githubStars,
    int $githubWatchers,
    int $githubForks,
    int $dependents,
    int $suggesters,
    int $favers,
    int $totalDownloads,
    int $monthlyDownloads,
    int $dailyDownloads,
    DateTimeImmutable $createdAt = new DateTimeImmutable(),
    DateTimeImmutable $updatedAt = null
  ) {
    $this->packageName      = $packageName;
    $this->githubStars      = $githubStars;
    $this->githubWatchers   = $githubWatchers;
    $this->githubForks      = $githubForks;
    $this->dependents       = $dependents;
    $this->suggesters       = $suggesters;
    $this->favers           = $favers;
    $this->totalDownloads   = $totalDownloads;
    $this->monthlyDownloads = $monthlyDownloads;
    $this->dailyDownloads   = $dailyDownloads;
    $this->createdAt        = $createdAt;
    $this->updatedAt        = $updatedAt;
  }

  public function getPackageName(): string {
    return $this->packageName;
  }

  public function getGithubStars(): int {
    return $this->githubStars;
  }

  public function withGithubStars(int $githubStars): self {
    if ($this->githubStars === $githubStars) {
      return $this;
    }

    $clone = clone $this;
    $clone->githubStars = $githubStars;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getGithubWatchers(): int {
    return $this->githubWatchers;
  }

  public function withGithubWatchers(int $githubWatchers): self {
    if ($this->githubWatchers === $githubWatchers) {
      return $this;
    }

    $clone = clone $this;
    $clone->githubWatchers = $githubWatchers;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getGithubForks(): int {
    return $this->githubForks;
  }

  public function withGithubForks(int $githubForks): self {
    if ($this->githubForks === $githubForks) {
      return $this;
    }

    $clone = clone $this;
    $clone->githubForks = $githubForks;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getDependents(): int {
    return $this->dependents;
  }

  public function withDependents(int $dependents): self {
    if ($this->dependents === $dependents) {
      return $this;
    }

    $clone = clone $this;
    $clone->dependents = $dependents;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getSuggesters(): int {
    return $this->suggesters;
  }

  public function withSuggesters(int $suggesters): self {
    if ($this->suggesters === $suggesters) {
      return $this;
    }

    $clone = clone $this;
    $clone->suggesters = $suggesters;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getFavers(): int {
    return $this->favers;
  }

  public function withFavers(int $favers): self {
    if ($this->favers === $favers) {
      return $this;
    }

    $clone = clone $this;
    $clone->favers = $favers;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getTotalDownloads(): int {
    return $this->totalDownloads;
  }

  public function withTotalDownloads(int $totalDownloads): self {
    if ($this->totalDownloads === $totalDownloads) {
      return $this;
    }

    $clone = clone $this;
    $clone->totalDownloads = $totalDownloads;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getMonthlyDownloads(): int {
    return $this->monthlyDownloads;
  }

  public function withMonthlyDownloads(int $monthlyDownloads): self {
    if ($this->monthlyDownloads === $monthlyDownloads) {
      return $this;
    }

    $clone = clone $this;
    $clone->monthlyDownloads = $monthlyDownloads;
    $clone->dirty = true;
    $clone->updatedAt = new DateTimeImmutable();

    return $clone;
  }

  public function getDailyDownloads(): int {
    return $this->dailyDownloads;
  }

  public function withDailyDownloads(int $dailyDownloads): self {
    if ($this->dailyDownloads === $dailyDownloads) {
      return $this;
    }

    $clone = clone $this;
    $clone->dailyDownloads = $dailyDownloads;
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
   *   packageName: string,
   *   githubStars: int,
   *   githubWatchers: int,
   *   githubForks: int,
   *   dependents: int,
   *   suggesters: int,
   *   favers: int,
   *   totalDownloads: int,
   *   monthlyDownloads: int,
   *   dailyDownloads: int,
   *   createdAt: \DateTimeImmutable,
   *   updatedAt: \DateTimeImmutable|null
   * }
   */
  #[ReturnTypeWillChange]
  public function jsonSerialize(): array {
    return [
      'packageName'      => $this->packageName,
      'githubStars'      => $this->githubStars,
      'githubWatchers'   => $this->githubWatchers,
      'githubForks'      => $this->githubForks,
      'dependents'       => $this->dependents,
      'suggesters'       => $this->suggesters,
      'favers'           => $this->favers,
      'totalDownloads'   => $this->totalDownloads,
      'monthlyDownloads' => $this->monthlyDownloads,
      'dailyDownloads'   => $this->dailyDownloads,
      'createdAt'        => $this->createdAt,
      'updatedAt'        => $this->updatedAt
    ];
  }
}
