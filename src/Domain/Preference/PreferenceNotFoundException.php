<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Preference;

use PackageHealth\PHP\Domain\Exception\DomainRecordNotFoundException;

class PreferenceNotFoundException extends DomainRecordNotFoundException {
  /**
   * @var string
   */
  protected $message = 'Preference not found';
}
