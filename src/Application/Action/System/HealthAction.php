<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\System;

use PackageHealth\PHP\Application\Action\AbstractAction;
use Psr\Http\Message\ResponseInterface;

final class HealthAction extends AbstractAction {
  protected function action(): ResponseInterface {
    return $this->response
      ->withStatus(204);
  }
}
