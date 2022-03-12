<?php
declare(strict_types = 1);

namespace App\Application\Action;

use JsonSerializable;
use ReturnTypeWillChange;

class ActionPayload implements JsonSerializable {
  private int $statusCode;

  /**
   * @var array|object|null
   */
  private $data;

  private ?ActionError $error;

  public function __construct(
    int $statusCode = 200,
    $data = null,
    ?ActionError $error = null
  ) {
    $this->statusCode = $statusCode;
    $this->data       = $data;
    $this->error      = $error;
  }

  public function getStatusCode(): int {
    return $this->statusCode;
  }

  /**
   * @return array|null|object
   */
  public function getData() {
    return $this->data;
  }

  public function getError(): ?ActionError {
    return $this->error;
  }

  #[ReturnTypeWillChange]
  public function jsonSerialize(): array {
    if ($this->data !== null) {
      if (is_array($this->data) && array_is_list($this->data)) {
        return [
          'status' => true,
          'list'   => $this->data
        ];
      }

      return [
        'status' => true,
        'data'   => $this->data
      ];
    }

    if ($this->error !== null) {
      return [
        'status' => false,
        'error'  => $this->error
      ];
    }

    return ['status' => true];
  }
}
