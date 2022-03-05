<?php
declare(strict_types = 1);

namespace App\Application\Actions\Maintenance;

use App\Application\Actions\AbstractAction;
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
