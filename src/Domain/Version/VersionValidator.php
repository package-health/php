<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Version;

use Composer\Semver\VersionParser;
use UnexpectedValueException;

final class VersionValidator {
  public static function isValidNumber(string $number): bool {
    try {
      (new VersionParser())->normalize($number);

      return true;
    } catch (UnexpectedValueException $exception) {
      return false;
    }
  }

  public static function assertValidNumber(string $number): void {
    if (self::isValidNumber($number) === false) {
      throw new VersionValidationException('Version number must match "X.Y.Z", or "vX.Y.Z", with an optional suffix for RC, beta, alpha or patch versions.');
    }
  }

  public static function isValidNormalizedNumber(string $normalized): bool {
    try {
      (new VersionParser())->normalize($normalized);

      return true;
    } catch (UnexpectedValueException $exception) {
      return false;
    }
  }

  public static function assertValidNormalizedNumber(string $normalized): void {
    if (self::isValidNormalizedNumber($normalized) === false) {
      throw new VersionValidationException('Normalized version number must match "W.X.Y.Z", with an optional suffix for RC, beta, alpha or patch versions.');
    }
  }
}
