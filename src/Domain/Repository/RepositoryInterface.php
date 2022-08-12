<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Repository;

interface RepositoryInterface {
  public function withLazyFetch(): static;
}
