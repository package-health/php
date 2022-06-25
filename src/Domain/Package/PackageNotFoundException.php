<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Package;

use PackageHealth\PHP\Domain\Exception\DomainRecordNotFoundException;

class PackageNotFoundException extends DomainRecordNotFoundException {
  /**
   * @var string
   */
  protected $message = 'Package not found';
}
