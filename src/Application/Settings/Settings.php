<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Settings;

use InvalidArgumentException;

final class Settings implements SettingsInterface {
  /**
   * @var array<string, mixed>
   */
  private array $config = [];
  /**
   * @var array<string, mixed>
   */
  private array $envVar = [];

  private function getPathEntry(string $entry) {
    $path = explode('.', $entry);
    $walk = $this->config;
    while (count($path)) {
      $item = array_shift($path);
      if (isset($walk[$item]) === false) {
        return null;
      }

      $walk = $walk[$item];
    }

    return $walk;
  }

  private function getPathValue(string $entry) {
    $value = $this->getPathEntry($entry);
    if ($value === null || is_array($value)) {
      return null;
    }

    return $value;
  }

  private function normalize(string $entry): string {
    return str_replace(['-', '.'], '_', strtoupper($entry));
  }

  public static function fromJson(string $path): self {
    $content = file_get_contents($path);
    if ($content === false) {
      throw new InvalidArgumentException(
        sprintf(
          'Failed to read content from "%s"',
          $path
        )
      );
    }

    return new self(
      json_decode(
        $content,
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

  public function has(string $entry): bool {
    $entry = $this->getPathEntry($entry);
    if ($entry === null) {
      $varName = $this->normalize($entry);

      return isset($this->envVar[$varName]);
    }

    return true;
  }

  public function getString(string $entry, string $default = ''): string {
    $value = $this->getPathValue($entry);
    if ($value === null) {
      $varName = $this->normalize($entry);
      if (isset($this->envVar[$varName])) {
        return (string)$this->envVar[$varName];
      }

      return $default;
    }

    return (string)$value;
  }

  public function getInt(string $entry, int $default = 0): int {
    $value = $this->getPathValue($entry);
    if ($value === null) {
      $varName = $this->normalize($entry);
      if (isset($this->envVar[$varName])) {
        return (int)$this->envVar[$varName];
      }

      return $default;
    }

    return (int)$value;
  }

  public function getFloat(string $entry, float $default = 0.0): float {
    $value = $this->getPathValue($entry);
    if ($value === null) {
      $varName = $this->normalize($entry);
      if (isset($this->envVar[$varName])) {
        return (float)$this->envVar[$varName];
      }

      return $default;
    }

    return (float)$value;
  }

  public function getBool(string $entry, bool $default = false): bool {
    $value = $this->getPathValue($entry);
    if ($value === null) {
      $varName = $this->normalize($entry);
      if (isset($this->envVar[$varName])) {
        return (bool)$this->envVar[$varName];
      }

      return $default;
    }

    return (bool)$value;
  }
}
