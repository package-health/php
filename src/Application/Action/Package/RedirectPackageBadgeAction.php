<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Package\PackageValidator;
use Psr\Http\Message\ResponseInterface;
use Slim\Routing\RouteContext;

final class RedirectPackageBadgeAction extends AbstractPackageAction {
  protected function action(): ResponseInterface {
    $vendor = $this->resolveStringArg('vendor');
    PackageValidator::assertValidVendor($vendor);

    $project = $this->resolveStringArg('project');
    PackageValidator::assertValidProject($project);

    $package = $this->packageRepository->get("{$vendor}/{$project}");

    $routeParser = RouteContext::fromRequest($this->request)->getRouteParser();

    $this->logger->debug("Badge for package '{$vendor}/{$project}' is being redirected.");

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
