<?php
declare(strict_types = 1);

namespace App\Application\Action\System;

use App\Application\Action\AbstractAction;
use Psr\Http\Message\ResponseInterface;

final class HealthAction extends AbstractAction {
  /**
   * {@inheritdoc}
   */
  protected function action(): ResponseInterface {
    return $this->response
      ->withStatus(204);
  }
}
