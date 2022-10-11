<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Message\Command;

use Courier\Message\CommandInterface;

final class PackagePurgeCommand implements CommandInterface {
  private string $packageName;
  /**
   * Force command execution (ie. skips command deduplication guards)
   */
  private bool $forceExecution;

  public function __construct(
    string $packageName,
    bool $forceExecution = false
  ) {
    $this->packageName    = $packageName;
    $this->forceExecution = $forceExecution;
  }

  public function getPackageName(): string {
    return $this->packageName;
  }

  public function forceExecution(): bool {
    return $this->forceExecution;
  }

  /**
   * @return array{
   *   0: string,
   *   1: bool
   * }
   */
  public function toArray(): array {
    return [$this->packageName, $this->forceExecution];
  }
}
