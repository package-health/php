<?php
declare(strict_types = 1);

namespace App\Application\Message\Event\Preference;

use App\Domain\Preference\Preference;
use Courier\Message\EventInterface;

abstract class AbstractPreferenceEvent implements EventInterface {
  protected Preference $preference;

  public function __construct(Preference $preference) {
    $this->preference = $preference;
  }

  public function getPreference(): Preference {
    return $this->preference;
  }

  /**
   * @return array{0: \App\Domain\Preference\Preference}
   */
  public function toArray(): array {
    return [$this->preference];
  }
}
