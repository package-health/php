<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Dependency;

use DateTimeImmutable;
use Kolekto\CollectionInterface;
use PackageHealth\PHP\Domain\Repository\RepositoryInterface;

interface DependencyRepositoryInterface extends RepositoryInterface {
  public function create(
    int $versionId,
    string $name,
    string $constraint,
    bool $development = false,
    DependencyStatusEnum $status = DependencyStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Dependency;

  public function all(array $orderBy = []): CollectionInterface;

  /**
   * @throws \PackageHealth\PHP\Domain\Dependency\DependencyNotFoundException
   */
  public function get(int $id): Dependency;

  public function find(
    array $query,
    int $limit = -1,
    int $offset = 0,
    array $orderBy = []
  ): CollectionInterface;

  public function save(Dependency $dependency): Dependency;

  public function update(Dependency $dependency): Dependency;

  public function delete(Dependency $dependency): void;
}
