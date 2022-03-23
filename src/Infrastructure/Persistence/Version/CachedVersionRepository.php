<?php
declare(strict_types = 1);

namespace App\Infrastructure\Persistence\Version;

use App\Domain\Version\Version;
use App\Domain\Version\VersionCollection;
use App\Domain\Version\VersionRepositoryInterface;
use App\Domain\Version\VersionStatusEnum;
use DateTimeImmutable;
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
    string $number,
    string $normalized,
    string $packageName,
    bool $release,
    VersionStatusEnum $status = VersionStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Version {
    return $this->versionRepository->create(
      $number,
      $normalized,
      $packageName,
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
   * @throws \App\Domain\Version\VersionNotFoundException
   */
  public function get(int $id): Version {
    $item = $this->cacheItemPool->getItem("/version/${id}");
    $version = $item->get();
    if ($item->isHit() === false ) {
      $version = $this->versionRepository->get($id);

      $item->set($version);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $version;
  }

  public function find(array $query): VersionCollection {
    $key = http_build_query($query);
    $item = $this->cacheItemPool->getItem("/version/find/{$key}");
    $versionCol = $item->get();
    if ($item->isHit() === false) {
      $versionCol = $this->versionRepository->find($query);

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
