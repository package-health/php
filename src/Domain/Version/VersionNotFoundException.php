<?php
declare(strict_types = 1);

namespace App\Domain\Version;

use App\Domain\Exception\DomainRecordNotFoundException;

class VersionNotFoundException extends DomainRecordNotFoundException {
  protected $message = 'Version not found';
}
