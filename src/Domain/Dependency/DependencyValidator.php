<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Dependency;

use Composer\Semver\VersionParser;
use UnexpectedValueException;

final class DependencyValidator {
  public static function isValidName(string $name): bool {
    return $name !== '';
  }

  public static function assertValidName(string $name): void {
    if (self::isValidName($name) === false) {
      throw new DependencyValidationException('Dependency name must not be empty');
    }
  }

  public static function isValidConstraint(string $constraint): bool {
    try {
      (new VersionParser())->parseConstraints($constraint);

      return true;
    } catch (UnexpectedValueException $exception) {
      return false;
    }
  }

  public static function assertValidConstraint(string $constraint): void {
    if (self::isValidConstraint($constraint) === false) {
      throw new DependencyValidationException(
        sprintf(
          'Invalid dependency constraint format "%s"',
          $constraint
        )
      );
    }
  }
}
