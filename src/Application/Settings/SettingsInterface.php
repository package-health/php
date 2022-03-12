<?php
declare(strict_types = 1);

namespace App\Application\Settings;

interface SettingsInterface {
  /**
   * @param string $key
   * @return mixed
   */
  public function get(string $key = '');

  public function has(string $category, string $entry): bool;

  public function getString(string $category, string $entry, string $default = ''): string;

  public function getInteger(string $category, string $entry, int $default = -1): int;
}
