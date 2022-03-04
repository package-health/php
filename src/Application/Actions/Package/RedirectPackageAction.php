<?php
declare(strict_types = 1);

namespace App\Application\Actions\Package;

use App\Domain\Package\Package;
use Psr\Http\Message\ResponseInterface;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class RedirectPackageAction extends AbstractPackageAction {
  /**
   * {@inheritdoc}
   */
  protected function action(): ResponseInterface {
    $vendor  = $this->resolveArg('vendor');
    $project = $this->resolveArg('project');
    $package = $this->packageRepository->get("${vendor}/${project}");

    if ($package->getLatestVersion() === '') {
      $twig = Twig::fromRequest($this->request);

      return $this->respondWithHtml(
        $twig->fetch(
          'package.twig',
          [
            'status'          => [
              'type' => 'is-dark'
            ],
            'package'         => $package,
            'version'         => [],
            'requiredDeps'    => [],
            'requiredDevDeps' => [],
            'notification' => [
              'type' => 'is-warning',
              'message' => 'This package has no public releases.'
            ],
            'show' => [
              'hero' => [
                'subtitle' => false,
                'footer'   => false
              ]
            ]
          ]
        )
      );
    }

    $routeParser = RouteContext::fromRequest($this->request)->getRouteParser();

    $this->logger->info("Package '${vendor}/${project}' is being redirected.");

    return $this->respondWithRedirect(
      $routeParser->urlFor(
        'viewPackage',
        [
          'vendor'  => $vendor,
          'project' => $project,
          'version' => $package->getLatestVersion() ?: 'unknown'
        ]
      )
    );
  }
}
