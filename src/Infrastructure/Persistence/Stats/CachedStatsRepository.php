<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Persistence\Stats;

use DateTimeImmutable;
use PackageHealth\PHP\Domain\Stats\Stats;
use PackageHealth\PHP\Domain\Stats\StatsCollection;
use PackageHealth\PHP\Domain\Stats\StatsRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;

final class CachedStatsRepository implements StatsRepositoryInterface {
  private StatsRepositoryInterface $statsRepository;
  private CacheItemPoolInterface $cacheItemPool;

  public function __construct(
    StatsRepositoryInterface $statsRepository,
    CacheItemPoolInterface $cacheItemPool
  ) {
    $this->statsRepository = $statsRepository;
    $this->cacheItemPool        = $cacheItemPool;
  }

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
  ): Stats {
    return $this->statsRepository->create(
      $packageName,
      $githubStars,
      $githubWatchers,
      $githubForks,
      $dependents,
      $suggesters,
      $favers,
      $totalDownloads,
      $monthlyDownloads,
      $dailyDownloads,
      $createdAt
    );
  }

  public function all(): StatsCollection {
    $item = $this->cacheItemPool->getItem('/stats');
    $statsCol = $item->get();
    if ($item->isHit() === false) {
      $statsCol = $this->statsRepository->all();

      $item->set($statsCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $statsCol;
  }

  public function findPopular(): StatsCollection {
    $item = $this->cacheItemPool->getItem('/stats/popular');
    $statsCol = $item->get();
    if ($item->isHit() === false) {
      $statsCol = $this->statsRepository->findPopular();

      $item->set($statsCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $statsCol;
  }

  public function exists(string $packageName): bool {
    $item = $this->cacheItemPool->getItem("/stats/{$packageName}/exists");
    $exists = $item->get();
    if ($item->isHit() === false) {
      $exists = $this->statsRepository->exists($packageName);

      $item->set($exists);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $exists;
  }

  /**
   * @throws \PackageHealth\PHP\Domain\Stats\StatsNotFoundException
   */
  public function get(string $packageName): Stats {
    $item = $this->cacheItemPool->getItem("/stats/{$packageName}");
    $stats = $item->get();
    if ($item->isHit() === false) {
      $stats = $this->statsRepository->get($packageName);

      $item->set($stats);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $stats;
  }

  public function find(array $query, int $limit = -1, int $offset = 0): StatsCollection {
    $key = http_build_query($query);
    $item = $this->cacheItemPool->getItem("/stats/find/{$key}/{$limit}/{$offset}");
    $statsCol = $item->get();
    if ($item->isHit() === false) {
      $statsCol = $this->statsRepository->find($query, $limit, $offset);

      $item->set($statsCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $statsCol;
  }

  public function save(Stats $stats): Stats {
    $stats = $this->statsRepository->save($stats);

    $this->cacheItemPool->deleteItem('/stats/' . $stats->getPackageName());

    return $stats;
  }

  public function update(Stats $stats): Stats {
    $stats = $this->statsRepository->update($stats);

    $this->cacheItemPool->deleteItem('/stats/' . $stats->getPackageName());

    return $stats;
  }
}
