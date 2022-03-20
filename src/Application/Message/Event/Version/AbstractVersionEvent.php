<?php
declare(strict_types = 1);

namespace App\Application\Message\Event\Version;

use App\Domain\Version\Version;
use Courier\Message\EventInterface;

abstract class AbstractVersionEvent implements EventInterface {
  protected Version $version;

  public function __construct(Version $version) {
    $this->version = $version;
  }

  public function getVersion(): Version {
    return $this->version;
  }

  /**
   * @return array{0: \App\Domain\Version\Version}
   */
  public function __serialize(): array {
    return [$this->version];
  }

  /**
   * @param array{0: \App\Domain\Version\Version} $data
   */
  public function __unserialize(array $data): void {
    $this->version = $data[0];
  }
}
