<?php
declare(strict_types = 1);

namespace App\Application\Listeners;

use App\Domain\Version\Version;

final class VersionListener {
  public function __construct() {}

  public function onCreated(Version $version): void {
    // echo 'Version created: ', $version->getId(), PHP_EOL;
  }

  public function onUpdated(Version $version): void {
    // echo 'Version updated: ', $version->getId(), PHP_EOL;
  }

  public function onDeleted(Version $version): void {
    echo 'Version deleted: ', $version->getId(), PHP_EOL;
  }
}
