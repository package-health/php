<?php
declare(strict_types = 1);

namespace App\Application\Action\Package;

use Psr\Http\Message\ResponseInterface;
use Slim\Routing\RouteContext;

final class RedirectPackageBadgeAction extends AbstractPackageAction {
  /**
   * {@inheritdoc}
   */
  protected function action(): ResponseInterface {
    $vendor  = $this->resolveStringArg('vendor');
    $project = $this->resolveStringArg('project');
    $package = $this->packageRepository->get("${vendor}/${project}");

    $routeParser = RouteContext::fromRequest($this->request)->getRouteParser();

    $this->logger->info("Badge for package '${vendor}/${project}' is being redirected.");

    return $this->respondWithRedirect(
      $routeParser->urlFor(
        'viewPackageBadge',
        [
          'vendor'  => $vendor,
          'project' => $project,
          'version' => (string)($package->getLatestVersion() ?: 'unknown')
        ]
      )
    );
  }
}
