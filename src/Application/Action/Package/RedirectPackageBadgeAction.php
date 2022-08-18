<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Package\PackageNotFoundException;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use Psr\Http\Message\ResponseInterface;
use Slim\Routing\RouteContext;

final class RedirectPackageBadgeAction extends AbstractPackageAction {
  protected function action(): ResponseInterface {
    $vendor = $this->resolveStringArg('vendor');
    PackageValidator::assertValidVendor($vendor);

    $project = $this->resolveStringArg('project');
    PackageValidator::assertValidProject($project);

    $item = $this->cacheItemPool->getItem("/view/redirectPackageBadge/{$vendor}/{$project}");
    $url  = $item->get();
    if ($item->isHit() === false) {
      $packageCol = $this->packageRepository->find(
        [
          'name' => "{$vendor}/{$project}"
        ],
        1
      );

      if ($packageCol->isEmpty()) {
        throw new PackageNotFoundException();
      }

      $this->logger->debug("Badge for package '{$vendor}/{$project}' is being redirected.");

      $package = $packageCol->first();
      $routeParser = RouteContext::fromRequest($this->request)->getRouteParser();
      $url = $routeParser->urlFor(
        'viewPackageBadge',
        [
          'vendor'  => $vendor,
          'project' => $project,
          'version' => (string)($package->getLatestVersion() ?: 'unknown')
        ]
      );

      $item->set($url);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $this->respondWithRedirect($url);
  }
}
