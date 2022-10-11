<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Persistence\Package;

use DateTimeImmutable;
use Kolekto\CollectionInterface;
use Kolekto\EagerCollection;
use Kolekto\LazyCollection;
use PackageHealth\PHP\Domain\Package\Package;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;

final class CachedPackageRepository implements PackageRepositoryInterface {
  private PackageRepositoryInterface $packageRepository;
  private CacheItemPoolInterface $cacheItemPool;

  public function __construct(
    PackageRepositoryInterface $packageRepository,
    CacheItemPoolInterface $cacheItemPool
  ) {
    $this->packageRepository = $packageRepository;
    $this->cacheItemPool     = $cacheItemPool;
  }

  public function withLazyFetch(): static {
    $this->packageRepository->withLazyFetch();

    return $this;
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

  public function all(array $orderBy = []): CollectionInterface {
    $key = http_build_query($orderBy) ?: 'no-order';
    $item = $this->cacheItemPool->getItem("/package/{$key}");
    $packageCol = $item->get();
    if ($item->isHit() === false) {
      $packageCol = $this->packageRepository->all($orderBy);

      $item->set($packageCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $packageCol;
  }

  public function findPopular(int $limit = 10): CollectionInterface {
    $item = $this->cacheItemPool->getItem("/package/popular/{$limit}");
    $packageCol = $item->get();
    if ($item->isHit() === false) {
      $packageCol = $this->packageRepository->findPopular($limit);
      if ($packageCol instanceof LazyCollection) {
        return $packageCol;
      }

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
   * @throws \PackageHealth\PHP\Domain\Package\PackageNotFoundException
   */
  public function get(int $id): Package {
    $item = $this->cacheItemPool->getItem("/package/{$id}");
    $package = $item->get();
    if ($item->isHit() === false) {
      $package = $this->packageRepository->get($id);

      $item->set($package);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $package;
  }

  public function find(
    array $query,
    int $limit = -1,
    int $offset = 0,
    array $orderBy = []
  ): CollectionInterface {
    $key = implode(
      '/',
      [
        http_build_query($query),
        http_build_query($orderBy) ?: 'no-order',
        $limit,
        $offset
      ]
    );
    $item = $this->cacheItemPool->getItem("/package/find/{$key}");
    $packageCol = $item->get();
    if ($item->isHit() === false) {
      $packageCol = $this->packageRepository->find($query, $limit, $offset, $orderBy);
      if ($packageCol instanceof LazyCollection) {
        return $packageCol;
      }

      $item->set($packageCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $packageCol;
  }

  public function findMatching(array $query, array $orderBy = []): CollectionInterface {
    $key = implode(
      '/',
      [
        http_build_query($query),
        http_build_query($orderBy) ?: 'no-order'
      ]
    );
    $item = $this->cacheItemPool->getItem("/package/matching/{$key}");
    $packageCol = $item->get();
    if ($item->isHit() === false) {
      $packageCol = $this->packageRepository->findMatching($query, $orderBy);
      if ($packageCol instanceof LazyCollection) {
        return $packageCol;
      }

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

  public function delete(Package $package): void {
    if ($package->getId() === null) {
      throw new InvalidArgumentException();
    }

    $this->cacheItemPool->deleteItem('/package/' . $package->getName());
    $this->versionRepository->delete($package);
  }
}
