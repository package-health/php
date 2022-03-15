<?php
declare(strict_types = 1);

namespace App\Domain\Package;

interface PackageRepositoryInterface {
  public function create(string $name): Package;

  public function all(): PackageCollection;

  public function findPopular(): PackageCollection;

  public function exists(string $name): bool;

  /**
   * @throws \App\Domain\Package\PackageNotFoundException
   */
  public function get(string $name): Package;

  public function find(array $query): PackageCollection;

  public function findMatching(array $query): PackageCollection;

  public function save(Package $package): Package;

  public function update(Package $package): Package;
}
