<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Repository;

use Kolekto\CollectionInterface;

interface RepositoryInterface {
  public function withLazyFetch(): static;
  public function all(array $orderBy = []): CollectionInterface;
  public function find(
    array $query,
    int $limit = -1,
    int $offset = 0,
    array $orderBy = []
  ): CollectionInterface;
}
