<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Preference;

use PackageHealth\PHP\Domain\Exception\DomainValidationException;

class PreferenceValidationException extends DomainValidationException {
  /**
   * @var string
   */
  protected $message = 'Preference validation exception';
}
