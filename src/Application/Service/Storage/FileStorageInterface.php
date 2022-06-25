<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Service\Storage;

interface FileStorageInterface {
  public function exists(string $file): bool;
  public function getModificationTime(string $file): int;
  public function setModificationTime(string $file, int $time): bool;
  public function writeContent(string $file, string $content): bool;
  public function readContent(string $file): string;
  public function delete(string $file): bool;
}
