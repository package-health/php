<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Message\Command;

use Courier\Message\CommandInterface;

final class PackageDiscoveryCommand implements CommandInterface {
  private string $packageName;
  /**
   * Force command execution (ie. skips command deduplication guards)
   */
  private bool $forceExecution;
  /**
   * Work in offline mode (ie. avoids connecting to packagist's mirror)
   */
  private bool $workOffline;

  public function __construct(
    string $packageName,
    bool $forceExecution = false,
    bool $workOffline = false
  ) {
    $this->packageName    = $packageName;
    $this->forceExecution = $forceExecution;
    $this->workOffline    = $workOffline;
  }

  public function getPackageName(): string {
    return $this->packageName;
  }

  public function forceExecution(): bool {
    return $this->forceExecution;
  }

  public function workOffline(): bool {
    return $this->workOffline;
  }

  /**
   * @return array{
   *   0: string,
   *   1: bool,
   *   2: bool
   * }
   */
  public function toArray(): array {
    return [$this->packageName, $this->forceExecution, $this->workOffline];
  }
}
