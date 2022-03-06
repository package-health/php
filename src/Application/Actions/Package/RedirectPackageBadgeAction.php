<?php
declare(strict_types = 1);

namespace App\Application\Actions\Package;

use Psr\Http\Message\ResponseInterface;
use Slim\Routing\RouteContext;

final class RedirectPackageBadgeAction extends AbstractPackageAction {
  /**
   * {@inheritdoc}
   */
  protected function action(): ResponseInterface {
    $vendor  = $this->resolveArg('vendor');
    $project = $this->resolveArg('project');
    $package = $this->packageRepository->get("${vendor}/${project}");

    $routeParser = RouteContext::fromRequest($this->request)->getRouteParser();

    $this->logger->info("Badge for package '${vendor}/${project}' is being redirected.");

    return $this->respondWithRedirect(
      $routeParser->urlFor(
        'viewPackageBadge',
        [
          'vendor'  => $vendor,
          'project' => $project,
          'version' => $package->getLatestVersion() ?: 'unknown'
        ]
      )
    );
  }
}
