<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Preference;

final class PreferenceValidator {
  public static function isValidCategory(string $category): bool {
    return $category !== '';
  }

  public static function assertValidCategory(string $category): void {
    if (self::isValidCategory($category) === false) {
      throw new PreferenceValidationException('Category must not be empty');
    }
  }

  public static function isValidProperty(string $property): bool {
    return $property !== '';
  }

  public static function assertValidProperty(string $property): void {
    if (self::isValidProperty($property) === false) {
      throw new PreferenceValidationException('Property must not be empty');
    }
  }
}
