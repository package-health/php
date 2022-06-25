<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use Psr\Http\Message\ResponseInterface;
use Slim\Routing\RouteContext;

final class RedirectListPackagesAction extends AbstractPackageAction {
  protected function action(): ResponseInterface {
    $routeParser = RouteContext::fromRequest($this->request)->getRouteParser();

    $this->logger->debug('Invalid route /packages is being redirected.');

    return $this->respondWithRedirect(
      $routeParser->urlFor('listPackages')
    );
  }
}
