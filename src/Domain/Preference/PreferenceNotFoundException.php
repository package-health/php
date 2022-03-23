<?php
declare(strict_types = 1);

namespace App\Domain\Preference;

use App\Domain\Exception\DomainRecordNotFoundException;

class PreferenceNotFoundException extends DomainRecordNotFoundException {
  /**
   * @var string
   */
  protected $message = 'Preference not found';
}
