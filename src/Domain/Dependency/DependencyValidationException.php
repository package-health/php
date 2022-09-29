<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Dependency;

use PackageHealth\PHP\Domain\Exception\DomainValidationException;

class DependencyValidationException extends DomainValidationException {
  /**
   * @var string
   */
  protected $message = 'Dependency validation exception';
}
