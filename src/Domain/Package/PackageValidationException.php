<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Package;

use PackageHealth\PHP\Domain\Exception\DomainValidationException;

class PackageValidationException extends DomainValidationException {
  /**
   * @var string
   */
  protected $message = 'Package validation exception';
}
