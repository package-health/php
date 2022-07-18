<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Version;

use PackageHealth\PHP\Domain\Exception\DomainValidationException;

class VersionValidationException extends DomainValidationException {
  /**
   * @var string
   */
  protected $message = 'Version validation exception';
}
