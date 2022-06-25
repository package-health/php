<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Persistence\Preference;

use DateTimeImmutable;
use PackageHealth\PHP\Domain\Preference\Preference;
use PackageHealth\PHP\Domain\Preference\PreferenceCollection;
use PackageHealth\PHP\Domain\Preference\PreferenceRepositoryInterface;
use PackageHealth\PHP\Domain\Preference\PreferenceTypeEnum;
use Psr\Cache\CacheItemPoolInterface;

final class CachedPreferenceRepository implements PreferenceRepositoryInterface {
  private PreferenceRepositoryInterface $preferenceRepository;
  private CacheItemPoolInterface $cacheItemPool;

  public function __construct(
    PreferenceRepositoryInterface $preferenceRepository,
    CacheItemPoolInterface $cacheItemPool
  ) {
    $this->preferenceRepository = $preferenceRepository;
    $this->cacheItemPool     = $cacheItemPool;
  }

  public function create(
    string $category,
    string $property,
    string $value,
    PreferenceTypeEnum $status = PreferenceTypeEnum::isString,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Preference {
    return $this->preferenceRepository->create(
      $category,
      $property,
      $value,
      $status,
      $createdAt
    );
  }

  public function all(): PreferenceCollection {
    $item = $this->cacheItemPool->getItem('/preference');
    $preferenceCol = $item->get();
    if ($item->isHit() === false) {
      $preferenceCol = $this->preferenceRepository->all();

      $item->set($preferenceCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $preferenceCol;
  }

  /**
   * @throws \PackageHealth\PHP\Domain\Preference\PreferenceNotFoundException
   */
  public function get(int $id): Preference {
    $item = $this->cacheItemPool->getItem("/preference/{$id}");
    $preference = $item->get();
    if ($item->isHit() === false ) {
      $preference = $this->preferenceRepository->get($id);

      $item->set($preference);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $preference;
  }

  public function find(array $query, int $limit = -1, int $offset = 0): PreferenceCollection {
    $key = http_build_query($query);
    $item = $this->cacheItemPool->getItem("/preference/find/{$key}/{$limit}/{$offset}");
    $preferenceCol = $item->get();
    if ($item->isHit() === false) {
      $preferenceCol = $this->preferenceRepository->find($query, $limit, $offset);

      $item->set($preferenceCol);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $preferenceCol;
  }

  public function save(Preference $preference): Preference {
    $preference = $this->preferenceRepository->save($preference);

    $this->cacheItemPool->deleteItem('/preference/' . $preference->getId());

    return $preference;
  }

  public function update(Preference $preference): Preference {
    $preference = $this->preferenceRepository->update($preference);

    $this->cacheItemPool->deleteItem('/preference/' . $preference->getId());

    return $preference;
  }
}
