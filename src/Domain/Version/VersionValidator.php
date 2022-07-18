<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Domain\Version;

use Composer\Semver\VersionParser;
use UnexpectedValueException;

final class VersionValidator {
  public static function isValid(string $version): bool {
    try {
      (new VersionParser())->normalize($version);

      return true;
    } catch (UnexpectedValueException $exception) {
      return false;
    }
  }

  public static function assertValid(string $version): void {
    if (self::isValid($version) === false) {
      throw new VersionValidationException('Version must match "X.Y.Z", or "vX.Y.Z", with an optional suffix for RC, beta, alpha or patch versions.');
    }
  }
}
