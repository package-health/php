<?php
declare(strict_types = 1);

namespace App\Infrastructure\Persistence\Package;

use App\Domain\Package\Package;
use App\Domain\Package\PackageCollection;
use App\Domain\Package\PackageRepositoryInterface;
use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;

final class CachedPackageRepository implements PackageRepositoryInterface {
  private PackageRepositoryInterface $packageRepository;
  private CacheItemPoolInterface $cacheItemPool;

  public function __construct(
    PackageRepositoryInterface $packageRepository,
    CacheItemPoolInterface $cacheItemPool
  ) {
    $this->packageRepository = $packageRepository;
    $this->cacheItemPool        = $cacheItemPool;
  }

  public function create(
    string $name,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Package {
    return $this->packageRepository->create(
      $name,
      $createdAt
    );
  }

  public function all(): PackageCollection {
    $item = $this->cacheItemPool->getItem('/package');
    $packageCol = $item->get();
    if ($item->isHit() === false) {
      $packageCol = $this->packageRepository->all();

      $item->set($packageCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $packageCol;
  }

  public function findPopular(int $limit = 10): PackageCollection {
    $item = $this->cacheItemPool->getItem("/package/popular/{$limit}");
    $packageCol = $item->get();
    if ($item->isHit() === false) {
      $packageCol = $this->packageRepository->findPopular($limit);

      $item->set($packageCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $packageCol;
  }

  public function exists(string $name): bool {
    $item = $this->cacheItemPool->getItem("/package/{$name}/exists");
    $exists = $item->get();
    if ($item->isHit() === false) {
      $exists = $this->packageRepository->exists($name);

      $item->set($exists);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $exists;
  }

  /**
   * @throws \App\Domain\Package\PackageNotFoundException
   */
  public function get(string $name): Package {
    $item = $this->cacheItemPool->getItem("/package/{$name}");
    $package = $item->get();
    if ($item->isHit() === false) {
      $package = $this->packageRepository->get($name);

      $item->set($package);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $package;
  }

  public function find(array $query): PackageCollection {
    $key = http_build_query($query);
    $item = $this->cacheItemPool->getItem("/package/find/{$key}");
    $packageCol = $item->get();
    if ($item->isHit() === false) {
      $packageCol = $this->packageRepository->find($query);

      $item->set($packageCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $packageCol;
  }

  public function findMatching(array $query): PackageCollection {
    $key = http_build_query($query);
    $item = $this->cacheItemPool->getItem("/package/matching/{$key}");
    $packageCol = $item->get();
    if ($item->isHit() === false) {
      $packageCol = $this->packageRepository->findMatching($query);

      $item->set($packageCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $packageCol;
  }

  public function save(Package $package): Package {
    $package = $this->packageRepository->save($package);

    $this->cacheItemPool->deleteItem('/package/' . $package->getName());

    return $package;
  }

  public function update(Package $package): Package {
    $package = $this->packageRepository->update($package);

    $this->cacheItemPool->deleteItem('/package/' . $package->getName());

    return $package;
  }
}
