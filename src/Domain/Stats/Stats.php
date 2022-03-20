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
    DateTimeImmutable $createdAt = null,
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
    $this->createdAt        = $createdAt ?? new DateTimeImmutable();
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
   *   package_name: string,
   *   github_stars: int,
   *   github_watchers: int,
   *   github_forks: int,
   *   dependents: int,
   *   suggesters: int,
   *   favers: int,
   *   total_downloads: int,
   *   monthly_downloads: int,
   *   daily_downloads: int,
   *   created_at: int,
   *   updated_at: int|null
   * }
   */
  #[ReturnTypeWillChange]
  public function jsonSerialize(): array {
    return [
      'package_name'      => $this->packageName,
      'github_stars'      => $this->githubStars,
      'github_watchers'   => $this->githubWatchers,
      'github_forks'      => $this->githubForks,
      'dependents'        => $this->dependents,
      'suggesters'        => $this->suggesters,
      'favers'            => $this->favers,
      'total_downloads'   => $this->totalDownloads,
      'monthly_downloads' => $this->monthlyDownloads,
      'daily_downloads'   => $this->dailyDownloads,
      'created_at'        => $this->createdAt->getTimestamp(),
      'updated_at'        => $this->updatedAt?->getTimestamp(),
    ];
  }
}
