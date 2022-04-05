<?php
declare(strict_types = 1);

namespace App\Infrastructure\Persistence\Preference;

use App\Domain\Preference\Preference;
use App\Domain\Preference\PreferenceCollection;
use App\Domain\Preference\PreferenceRepositoryInterface;
use App\Domain\Preference\PreferenceTypeEnum;
use DateTimeImmutable;
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
   * @throws \App\Domain\Preference\PreferenceNotFoundException
   */
  public function get(int $id): Preference {
    $item = $this->cacheItemPool->getItem("/preference/${id}");
    $preference = $item->get();
    if ($item->isHit() === false ) {
      $preference = $this->preferenceRepository->get($id);

      $item->set($preference);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $preference;
  }

  public function find(array $query): PreferenceCollection {
    $key = http_build_query($query);
    $item = $this->cacheItemPool->getItem("/preference/find/{$key}");
    $preferenceCol = $item->get();
    if ($item->isHit() === false) {
      $preferenceCol = $this->preferenceRepository->find($query);

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