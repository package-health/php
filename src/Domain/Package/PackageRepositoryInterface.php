<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Package;

use DateTimeImmutable;

interface PackageRepositoryInterface {
  public function create(
    string $name,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Package;

  public function all(): PackageCollection;

  public function findPopular(int $limit = 10): PackageCollection;

  public function exists(string $name): bool;

  /**
   * @throws \App\Domain\Package\PackageNotFoundException
   */
  public function get(string $name): Package;

  public function find(array $query, int $limit = -1, int $offset = 0): PackageCollection;

  public function findMatching(array $query): PackageCollection;

  public function save(Package $package): Package;

  public function update(Package $package): Package;
}
