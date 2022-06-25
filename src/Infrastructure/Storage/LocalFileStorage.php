<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Infrastructure\Storage;

use InvalidArgumentException;
use PackageHealth\PHP\Application\Service\Storage\FileStorageInterface;
use RuntimeException;

final class LocalFileStorage implements FileStorageInterface {
  private string $path;

  private function getPath(string $file): string {
    return sprintf(
      '%s/%s',
      $this->path,
      ltrim($file, '/')
    );
  }

  /**
   * @throws \InvalidArgumentException
   */
  private function makePath(string $path): void {
    if (is_dir($path) === false && mkdir($path, recursive: true) === false) {
      throw new InvalidArgumentException(
        sprintf(
          'Invalid path "%s"',
          $path
        )
      );
    }
  }

  /**
   * @throws \InvalidArgumentException
   */
  public function __construct(string $path) {
    if (is_dir($path) === false && mkdir($path, recursive: true) === false) {
      throw new InvalidArgumentException(
        sprintf(
          'Invalid path "%s"',
          $path
        )
      );
    }

    $this->path = rtrim($path, '/');
  }

  public function exists(string $file): bool {
    $path = $this->getPath($file);

    return file_exists($path);
  }

  /**
   * @throws \InvalidArgumentException
   * @throws \RuntimeException
   */
  public function getModificationTime(string $file): int {
    $path = $this->getPath($file);
    if (file_exists($path)) {
      $mtime = filemtime($path);
      if ($mtime === false) {
        throw new RuntimeException(
          sprintf(
            'Failed to get modification time of "%s"',
            $file
          )
        );
      }

      return $mtime;
    }

    throw new InvalidArgumentException(
      sprintf(
        'File "%s" does not exist',
        $file
      )
    );
  }

  public function setModificationTime(string $file, int $time): bool {
    $path = $this->getPath($file);

    return touch($path, $time);
  }

  public function writeContent(string $file, string $content): bool {
    $path = $this->getPath($file);
    $this->makePath(dirname($path));

    return file_put_contents($path, $content, LOCK_EX) === strlen($content);
  }

  /**
   * @throws \InvalidArgumentException
   * @throws \RuntimeException
   */
  public function readContent(string $file): string {
    $path = $this->getPath($file);
    if (file_exists($path)) {
      $content = file_get_contents($path);
      if ($content === false) {
        throw new RuntimeException(
          sprintf(
            'Failed to get file content of "%s"',
            $file
          )
        );
      }

      return $content;
    }

    throw new InvalidArgumentException(
      sprintf(
        'File "%s" does not exist',
        $file
      )
    );
  }

  public function delete(string $file): bool {
    $path = $this->getPath($file);

    if (file_exists($path)) {
      return unlink($path);
    }

    return true;
  }
}
