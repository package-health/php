<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Stats;

use DateTimeImmutable;
use Kolekto\CollectionInterface;
use PackageHealth\PHP\Domain\Repository\RepositoryInterface;

interface StatsRepositoryInterface extends RepositoryInterface {
  public function create(
    string $packageName,
    int $githubStars = 0,
    int $githubWatchers = 0,
    int $githubForks = 0,
    int $dependents = 0,
    int $suggesters = 0,
    int $favers = 0,
    int $totalDownloads = 0,
    int $monthlyDownloads = 0,
    int $dailyDownloads = 0,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Stats;

  public function all(array $orderBy = []): CollectionInterface;

  public function findPopular(): CollectionInterface;

  public function exists(string $packageName): bool;

  /**
   * @throws \PackageHealth\PHP\Domain\Stats\StatsNotFoundException
   */
  public function get(string $packageName): Stats;

  public function find(
    array $query,
    int $limit = -1,
    int $offset = 0,
    array $orderBy = []
  ): CollectionInterface;

  public function save(Stats $stats): Stats;

  public function update(Stats $stats): Stats;
}
