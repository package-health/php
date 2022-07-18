<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Exception;

class DomainValidationException extends DomainException {
  /**
   * @var string
   */
  protected $message = 'Record validation exception';
}
