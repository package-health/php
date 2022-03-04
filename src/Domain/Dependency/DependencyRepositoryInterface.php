<?php
declare(strict_types = 1);

namespace App\Domain\Dependency;

interface DependencyRepositoryInterface {

  public function create(
    int $versionId,
    string $name,
    string $constraint,
    bool $development = false,
    DependencyStatusEnum $status = DependencyStatusEnum::Unknown,
  ): Dependency;

  /**
   * @return \App\Domain\Dependency[]
   */
  public function all(): array;

  /**
   * @throws \App\Domain\Dependency\DependencyNotFoundException
   */
  public function get(int $id): Dependency;

  public function find(array $query): array;

  public function save(Dependency $dependency): Dependency;

  public function update(Dependency $dependency): Dependency;
}
