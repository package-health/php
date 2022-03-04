<?php
declare(strict_types = 1);

namespace App\Domain\Version;

interface VersionRepositoryInterface {

  public function create(
    string $number,
    string $normalized,
    string $packageName,
    bool $release,
    VersionStatusEnum $status
  ): Version;

  /**
   * @return \App\Domain\Version[]
   */
  public function all(): array;

  /**
   * @throws \App\Domain\Version\VersionNotFoundException
   */
  public function get(int $id): Version;

  public function find(array $query): array;

  public function save(Version $version): Version;

  public function update(Version $version): Version;
}
