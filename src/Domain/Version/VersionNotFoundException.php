<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Version;

use PackageHealth\PHP\Domain\Exception\DomainRecordNotFoundException;

class VersionNotFoundException extends DomainRecordNotFoundException {
  /**
   * @var string
   */
  protected $message = 'Version not found';
}
