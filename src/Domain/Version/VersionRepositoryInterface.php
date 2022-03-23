<?php
declare(strict_types = 1);

namespace App\Domain\Version;

use DateTimeImmutable;

interface VersionRepositoryInterface {
  public function create(
    string $number,
    string $normalized,
    string $packageName,
    bool $release,
    VersionStatusEnum $status = VersionStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Version;

  public function all(): VersionCollection;

  /**
   * @throws \App\Domain\Version\VersionNotFoundException
   */
  public function get(int $id): Version;

  public function find(array $query): VersionCollection;

  public function save(Version $version): Version;

  public function update(Version $version): Version;
}
