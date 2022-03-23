<?php
declare(strict_types = 1);

namespace App\Domain\Preference;

enum PreferenceTypeEnum: string {
  case isString = 'string';
  case isInteger = 'integer';
  case isFloat = 'float';
  case isBool = 'bool';
}
