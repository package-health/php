<?php
declare(strict_types = 1);

namespace App\Domain\Dependency;

interface DependencyRepositoryInterface {

  public function create(
    int $versionId,
    string $name,
    string $constraint,
    bool $development = false,
    DependencyStatusEnum $status = DependencyStatusEnum::Unknown
  ): Dependency;

  public function all(): DependencyCollection;

  /**
   * @throws \App\Domain\Dependency\DependencyNotFoundException
   */
  public function get(int $id): Dependency;

  public function find(array $query): DependencyCollection;

  public function save(Dependency $dependency): Dependency;

  public function update(Dependency $dependency): Dependency;
}
