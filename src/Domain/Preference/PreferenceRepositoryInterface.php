<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Preference;

use DateTimeImmutable;
use Kolekto\CollectionInterface;
use PackageHealth\PHP\Domain\Repository\RepositoryInterface;

interface PreferenceRepositoryInterface extends RepositoryInterface {
  public function create(
    string $category,
    string $property,
    string $value,
    PreferenceTypeEnum $type = PreferenceTypeEnum::isString,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Preference;

  public function all(array $orderBy = []): CollectionInterface;

  /**
   * @throws \PackageHealth\PHP\Domain\Preference\PreferenceNotFoundException
   */
  public function get(int $id): Preference;

  public function find(
    array $query,
    int $limit = -1,
    int $offset = 0,
    array $orderBy = []
  ): CollectionInterface;

  public function save(Preference $preference): Preference;

  public function update(Preference $preference): Preference;
}
