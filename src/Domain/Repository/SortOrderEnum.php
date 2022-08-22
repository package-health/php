<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Repository;

enum SortOrderEnum: string {
  case ASC = 'ASC';
  case DESC = 'DESC';
}
