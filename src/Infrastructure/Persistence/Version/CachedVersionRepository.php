<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Persistence\Version;

use DateTimeImmutable;
use Kolekto\CollectionInterface;
use Kolekto\EagerCollection;
use Kolekto\LazyCollection;
use PackageHealth\PHP\Domain\Version\Version;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionStatusEnum;
use Psr\Cache\CacheItemPoolInterface;

final class CachedVersionRepository implements VersionRepositoryInterface {
  private VersionRepositoryInterface $versionRepository;
  private CacheItemPoolInterface $cacheItemPool;

  public function __construct(
    VersionRepositoryInterface $versionRepository,
    CacheItemPoolInterface $cacheItemPool
  ) {
    $this->versionRepository = $versionRepository;
    $this->cacheItemPool     = $cacheItemPool;
  }

  public function withLazyFetch(): static {
    $this->dependencyRepository->withLazyFetch();

    return $this;
  }

  public function create(
    int $packageId,
    string $number,
    string $normalized,
    bool $release,
    VersionStatusEnum $status = VersionStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Version {
    return $this->versionRepository->create(
      $packageId,
      $number,
      $normalized,
      $release,
      $status,
      $createdAt
    );
  }

  public function all(array $orderBy = []): CollectionInterface {
    $key = http_build_query($orderBy) ?: 'no-order';
    $item = $this->cacheItemPool->getItem("/version/{$key}");
    $versionCol = $item->get();
    if ($item->isHit() === false) {
      $versionCol = $this->versionRepository->all();
      if ($versionCol instanceof LazyCollection) {
        return $versionCol;
      }

      $item->set($versionCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $versionCol;
  }

  /**
   * @throws \PackageHealth\PHP\Domain\Version\VersionNotFoundException
   */
  public function get(int $id): Version {
    $item = $this->cacheItemPool->getItem("/version/{$id}");
    $version = $item->get();
    if ($item->isHit() === false ) {
      $version = $this->versionRepository->get($id);

      $item->set($version);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $version;
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
    $item = $this->cacheItemPool->getItem("/version/find/{$key}");
    $versionCol = $item->get();
    if ($item->isHit() === false) {
      $versionCol = $this->versionRepository->find($query, $limit, $offset);
      if ($versionCol instanceof LazyCollection) {
        return $versionCol;
      }

      $item->set($versionCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $versionCol;
  }

  public function save(Version $version): Version {
    $version = $this->versionRepository->save($version);

    $this->cacheItemPool->deleteItem('/version/' . $version->getId());

    return $version;
  }

  public function update(Version $version): Version {
    $version = $this->versionRepository->update($version);

    $this->cacheItemPool->deleteItem('/version/' . $version->getId());

    return $version;
  }
}
