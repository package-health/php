<?php
declare(strict_types = 1);

namespace App\Domain\Dependency;

use App\Domain\Exception\DomainRecordNotFoundException;

class DependencyNotFoundException extends DomainRecordNotFoundException {
  protected $message = 'Dependency not found';
}
