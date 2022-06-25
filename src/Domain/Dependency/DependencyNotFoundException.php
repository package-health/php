<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Dependency;

use PackageHealth\PHP\Domain\Exception\DomainRecordNotFoundException;

class DependencyNotFoundException extends DomainRecordNotFoundException {
  protected $message = 'Dependency not found';
}
