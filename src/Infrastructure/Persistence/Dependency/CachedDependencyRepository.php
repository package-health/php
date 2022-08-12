<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Persistence\Dependency;

use DateTimeImmutable;
use Kolekto\CollectionInterface;
use Kolekto\EagerCollection;
use Kolekto\LazyCollection;
use PackageHealth\PHP\Domain\Dependency\Dependency;
use PackageHealth\PHP\Domain\Dependency\DependencyRepositoryInterface;
use PackageHealth\PHP\Domain\Dependency\DependencyStatusEnum;
use Psr\Cache\CacheItemPoolInterface;

final class CachedDependencyRepository implements DependencyRepositoryInterface {
  private DependencyRepositoryInterface $dependencyRepository;
  private CacheItemPoolInterface $cacheItemPool;

  public function __construct(
    DependencyRepositoryInterface $dependencyRepository,
    CacheItemPoolInterface $cacheItemPool
  ) {
    $this->dependencyRepository = $dependencyRepository;
    $this->cacheItemPool        = $cacheItemPool;
  }

  public function withLazyFetch(): static {
    $this->dependencyRepository->withLazyFetch();

    return $this;
  }

  public function create(
    int $versionId,
    string $name,
    string $constraint,
    bool $development = false,
    DependencyStatusEnum $status = DependencyStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Dependency {
    return $this->dependencyRepository->create(
      $versionId,
      $name,
      $constraint,
      $development,
      $status,
      $createdAt
    );
  }

  public function all(array $orderBy = []): CollectionInterface {
    $key = http_build_query($orderBy) ?: 'no-order';
    $item = $this->cacheItemPool->getItem("/dependency/{$key}");
    $dependencyCol = $item->get();
    if ($item->isHit() === false) {
      $dependencyCol = $this->dependencyRepository->all();
      if ($dependencyCol instanceof LazyCollection) {
        return $dependencyCol;
      }

      $item->set($dependencyCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $dependencyCol;
  }

  /**
   * @throws \PackageHealth\PHP\Domain\Dependency\DependencyNotFoundException
   */
  public function get(int $id): Dependency {
    $item = $this->cacheItemPool->getItem("/dependency/{$id}");
    $dependency = $item->get();
    if ($item->isHit() === false) {
      $dependency = $this->dependencyRepository->get($id);

      $item->set($dependency);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $dependency;
  }

  public function find(
    array $query,
    int $limit = -1,
    int $offset = 0,
    array $orderBy = []
  ): CollectionInterface {
    $key = implode(
      '/',
      array_filter([
        http_build_query($query),
        http_build_query($orderBy) ?: 'no-order',
        $limit,
        $offset,
      ])
    );
    $item = $this->cacheItemPool->getItem("/dependency/find/{$key}");
    $dependencyCol = $item->get();
    if ($item->isHit() === false) {
      $dependencyCol = $this->dependencyRepository->find($query, $limit, $offset);
      if ($dependencyCol instanceof LazyCollection) {
        return $dependencyCol;
      }

      $item->set($dependencyCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $dependencyCol;
  }

  public function save(Dependency $dependency): Dependency {
    $dependency = $this->dependencyRepository->save($dependency);

    $this->cacheItemPool->deleteItem('/dependency/' . $dependency->getId());

    return $dependency;
  }

  public function update(Dependency $dependency): Dependency {
    $dependency = $this->dependencyRepository->update($dependency);

    $this->cacheItemPool->deleteItem('/dependency/' . $dependency->getId());

    return $dependency;
  }
}
