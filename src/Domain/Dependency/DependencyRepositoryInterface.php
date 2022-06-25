<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Dependency;

use DateTimeImmutable;

interface DependencyRepositoryInterface {
  public function create(
    int $versionId,
    string $name,
    string $constraint,
    bool $development = false,
    DependencyStatusEnum $status = DependencyStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Dependency;

  public function all(): DependencyCollection;

  /**
   * @throws \PackageHealth\PHP\Domain\Dependency\DependencyNotFoundException
   */
  public function get(int $id): Dependency;

  public function find(array $query, int $limit = -1, int $offset = 0): DependencyCollection;

  public function save(Dependency $dependency): Dependency;

  public function update(Dependency $dependency): Dependency;
}
