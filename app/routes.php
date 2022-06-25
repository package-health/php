<?php
declare(strict_types = 1);

use PackageHealth\PHP\Application\Action\Package\ListPackagesAction;
use PackageHealth\PHP\Application\Action\Package\ListPackageVersionsAction;
use PackageHealth\PHP\Application\Action\Package\RedirectListPackagesAction;
use PackageHealth\PHP\Application\Action\Package\RedirectPackageAction;
use PackageHealth\PHP\Application\Action\Package\RedirectPackageBadgeAction;
use PackageHealth\PHP\Application\Action\Package\ViewPackageAction;
use PackageHealth\PHP\Application\Action\Package\ViewPackageBadgeAction;
use PackageHealth\PHP\Application\Action\System\HealthAction;
use PackageHealth\PHP\Application\Action\Vendor\ListVendorPackagesAction;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return static function (App $app): void {
  $app->options(
    '/{routes:.*}',
    function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
      // CORS Pre-Flight OPTIONS Request Handler
      return $response;
    }
  );

  $app->get('/', ListPackagesAction::class)
    ->setName('listPackages');

  $app->get('/health', HealthAction::class);

  $app->group(
    '/packages',
    function (Group $group) {
      $group
        ->get('', RedirectListPackagesAction::class);
      $group
        ->get('/{vendor}', ListVendorPackagesAction::class)
        ->setName('listVendorPackages');
      $group
        ->get('/{vendor}/{project}/status.svg', RedirectPackageBadgeAction::class)
        ->setName('redirectPackageBadge');
      $group
        ->get('/{vendor}/{project}/{version}/status.svg', ViewPackageBadgeAction::class)
        ->setName('viewPackageBadge');
      $group
        ->get('/{vendor}/{project}', ListPackageVersionsAction::class)
        ->setName('listPackageVersions');
      $group
        ->get('/{vendor}/{project}/latest', RedirectPackageAction::class)
        ->setName('redirectPackage');
      $group
        ->get('/{vendor}/{project}/{version}', ViewPackageAction::class)
        ->setName('viewPackage');
    }
  );

  /**
   * Catch-all route to serve a 404 Not Found page if none of the routes match
   * NOTE: make sure this route is defined last
   */
  $app->map(
    [
      'GET',
      'POST',
      'PUT',
      'DELETE',
      'PATCH'
    ],
    '/{routes:.+}',
    function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
      throw new HttpNotFoundException($request);
    }
  );
};
