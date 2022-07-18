<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Package;

final class PackageValidator {
  public static function isValidVendor(string $vendor): bool {
    return preg_match('/^[a-z0-9][a-z0-9._-]+$/', $vendor) === 1;
  }

  public static function assertValidVendor(string $vendor): void {
    if (self::isValidVendor($vendor) === false) {
      throw new PackageValidationException('Vendor names must start with a lowercase letter or a number and must only contain lowercase letters (a-z), numbers (0-9), and the characters ".", "-" and "_".');
    }
  }

  public static function isValidProject(string $project): bool {
    return preg_match('/^[a-z0-9][a-z0-9._-]+$/', $project) === 1;
  }

  public static function assertValidProject(string $project): void {
    if (self::isValidProject($project) === false) {
      throw new PackageValidationException('Project names must start with a lowercase letter or a number and must only contain lowercase letters (a-z), numbers (0-9), and the characters ".", "-" and "_".');
    }
  }

  public static function isValid(string $package): bool {
    return preg_match('/^[a-z0-9][a-z0-9._-]+\/[a-z0-9][a-z0-9._-]+$/', $package) === 1;
  }

  public static function assertValid(string $package): void {
    if (self::isValid($package) === false) {
      throw new PackageValidationException('Invalid package name');
    }
  }
}
