<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Version;

use DateTimeImmutable;
use Kolekto\CollectionInterface;
use PackageHealth\PHP\Domain\Repository\RepositoryInterface;

interface VersionRepositoryInterface extends RepositoryInterface {
  public function create(
    int $packageId,
    string $number,
    string $normalized,
    bool $release,
    VersionStatusEnum $status = VersionStatusEnum::Unknown,
    DateTimeImmutable $createdAt = new DateTimeImmutable()
  ): Version;

  public function all(array $orderBy = []): CollectionInterface;

  /**
   * @throws \PackageHealth\PHP\Domain\Version\VersionNotFoundException
   */
  public function get(int $id): Version;

  public function find(
    array $query,
    int $limit = -1,
    int $offset = 0,
    array $orderBy = []
  ): CollectionInterface;

  public function save(Version $version): Version;

  public function update(Version $version): Version;

  public function delete(Version $version): void;
}
