<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Persistence\Version;

use DateTimeImmutable;
use PackageHealth\PHP\Domain\Version\Version;
use PackageHealth\PHP\Domain\Version\VersionCollection;
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

  public function all(): VersionCollection {
    $item = $this->cacheItemPool->getItem('/version');
    $versionCol = $item->get();
    if ($item->isHit() === false) {
      $versionCol = $this->versionRepository->all();

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

  public function find(array $query, int $limit = -1, int $offset = 0): VersionCollection {
    $key = http_build_query($query);
    $item = $this->cacheItemPool->getItem("/version/find/{$key}/{$limit}/{$offset}");
    $versionCol = $item->get();
    if ($item->isHit() === false) {
      $versionCol = $this->versionRepository->find($query, $limit, $offset);

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
