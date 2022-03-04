<?php
declare(strict_types = 1);

namespace App\Domain\Exception;

class DomainRecordNotFoundException extends DomainException {
  protected $message = 'Record not found';
}
