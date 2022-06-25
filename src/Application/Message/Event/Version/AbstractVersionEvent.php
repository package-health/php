<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Message\Event\Version;

use Courier\Message\EventInterface;
use PackageHealth\PHP\Domain\Version\Version;

abstract class AbstractVersionEvent implements EventInterface {
  protected Version $version;

  public function __construct(Version $version) {
    $this->version = $version;
  }

  public function getVersion(): Version {
    return $this->version;
  }

  /**
   * @return array{0: \PackageHealth\PHP\Domain\Version\Version}
   */
  public function toArray(): array {
    return [$this->version];
  }
}
