<?php
declare(strict_types = 1);

namespace App\Domain\Package;

interface PackageRepositoryInterface {
  public function create(string $name): Package;

  /**
   * @return \App\Domain\Package[]
   */
  public function all(): array;

  /**
   * @return \App\Domain\Package[]
   */
  public function findPopular(): array;

  /**
   * @param string $name
   */
  public function exists(string $name): bool;

  /**
   * @param string $name
   *
   * @return \App\Domain\Package
   *
   * @throws \App\Domain\Package\PackageNotFoundException
   */
  public function get(string $name): Package;

  public function find(array $query): array;

  public function findMatching(array $query): array;

  public function save(Package $package): Package;

  public function update(Package $package): Package;
}
