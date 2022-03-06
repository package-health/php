<?php
declare(strict_types = 1);

namespace App\Domain\Package;

use App\Domain\Exception\DomainRecordNotFoundException;

class PackageNotFoundException extends DomainRecordNotFoundException {
  /**
   * @var string
   */
  protected $message = 'Package not found';
}
