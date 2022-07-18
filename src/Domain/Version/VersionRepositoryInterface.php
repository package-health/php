<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Version;

use DateTimeImmutable;

interface VersionRepositoryInterface {
  public function create(
    int $packageId,
    string $number,
    string $normalized,
    bool $release,
    VersionStatusEnum $status = VersionStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Version;

  public function all(): VersionCollection;

  /**
   * @throws \PackageHealth\PHP\Domain\Version\VersionNotFoundException
   */
  public function get(int $id): Version;

  public function find(array $query, int $limit = -1, int $offset = 0): VersionCollection;

  public function save(Version $version): Version;

  public function update(Version $version): Version;
}
