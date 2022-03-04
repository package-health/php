<?php
declare(strict_types = 1);

namespace App\Domain\Stats;

interface StatsRepositoryInterface {
  public function create(
    string $packageName,
    int $githubStars,
    int $githubWatchers,
    int $githubForks,
    int $dependents,
    int $suggesters,
    int $favers,
    int $totalDownloads,
    int $monthlyDownloads,
    int $dailyDownloads
  ): Stats;

  /**
   * @return \App\Domain\Stats[]
   */
  public function all(): array;

  /**
   * @param string $packageName
   */
  public function exists(string $packageName): bool;

  /**
   * @param string $packageName
   *
   * @return \App\Domain\Stats
   *
   * @throws \App\Domain\Stats\StatsNotFoundException
   */
  public function get(string $packageName): Stats;

  public function find(array $query): array;

  public function save(Stats $stats): Stats;

  public function update(Stats $stats): Stats;
}
