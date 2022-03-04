<?php
declare(strict_types = 1);

namespace App\Application\Actions\Package;

use Psr\Http\Message\ResponseInterface;
use Slim\Routing\RouteContext;

final class RedirectListPackagesAction extends AbstractPackageAction {
  /**
   * {@inheritdoc}
   */
  protected function action(): ResponseInterface {
    $routeParser = RouteContext::fromRequest($this->request)->getRouteParser();

    $this->logger->info('Invalid route /packages is being redirected.');

    return $this->respondWithRedirect(
      $routeParser->urlFor('listPackages')
    );
  }
}
