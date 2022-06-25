<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Exception;

class DomainRecordNotFoundException extends DomainException {
  /**
   * @var string
   */
  protected $message = 'Record not found';
}
