<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Package;

use DateTimeImmutable;
use Kolekto\CollectionInterface;
use PackageHealth\PHP\Domain\Repository\RepositoryInterface;

interface PackageRepositoryInterface extends RepositoryInterface {
  public function create(
    string $name,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Package;

  public function all(array $orderBy = []): CollectionInterface;

  public function findPopular(int $limit = 10): CollectionInterface;

  public function exists(string $name): bool;

  /**
   * @throws \PackageHealth\PHP\Domain\Package\PackageNotFoundException
   */
  public function get(int $id): Package;

  public function find(
    array $query,
    int $limit = -1,
    int $offset = 0,
    array $orderBy = []
  ): CollectionInterface;

  public function findMatching(array $query, array $orderBy = []): CollectionInterface;

  public function save(Package $package): Package;

  public function update(Package $package): Package;
}
