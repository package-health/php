<?php
declare(strict_types = 1);

namespace App\Application\Settings;

final class Settings implements SettingsInterface {
  private array $config = [];
  private array $envVar = [];

  private function normalize(string $category, string $entry): string {
    return sprintf(
      '%s_%s',
      str_replace('-', '_', strtoupper($category)),
      str_replace('-', '_', strtoupper($entry))
    );
  }

  public static function fromJson(string $path): static {
    return new static(
      json_decode(
        file_get_contents($path),
        true,
        512,
        JSON_THROW_ON_ERROR
      )
    );
  }

  public function __construct(array $config) {
    if (empty($config)) {
      $this->envVar = array_merge($_ENV, $_SERVER, getenv());

      return;
    }

    $this->config = $config;
  }

  /**
   * @return mixed
   */
  public function get(string $key = '') {
    return (empty($key)) ? $this->config : $this->config[$key];
  }

  public function has(string $category, string $entry): bool {
    if (isset($this->config[$category][$entry]) === true) {
      return true;
    }

    $varName = $this->normalize($category, $entry);

    return isset($this->envVar[$varName]);
  }

  public function getString(string $category, string $entry, string $default = ''): string {
    if (isset($this->config[$category][$entry]) === true) {
      return (string)$this->config[$category][$entry];
    }

    $varName = $this->normalize($category, $entry);
    if (isset($this->envVar[$varName])) {
      return (string)$this->envVar[$varName];
    }

    return $default;
  }

  public function getInteger(string $category, string $entry, int $default = -1): int {
    if (isset($this->config[$category][$entry]) === true) {
      return (int)$this->config[$category][$entry];
    }

    $varName = $this->normalize($category, $entry);
    if (isset($this->envVar[$varName])) {
      return (int)$this->envVar[$varName];
    }

    return $default;
  }
}
