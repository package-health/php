<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Message\Event\Preference;

use Courier\Message\EventInterface;
use PackageHealth\PHP\Domain\Preference\Preference;

abstract class AbstractPreferenceEvent implements EventInterface {
  protected Preference $preference;

  public function __construct(Preference $preference) {
    $this->preference = $preference;
  }

  public function getPreference(): Preference {
    return $this->preference;
  }

  /**
   * @return array{0: \PackageHealth\PHP\Domain\Preference\Preference}
   */
  public function toArray(): array {
    return [$this->preference];
  }
}
