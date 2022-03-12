<?php
declare(strict_types = 1);

use App\Application\Action\Package\ListPackagesAction;
use App\Application\Action\Package\RedirectListPackagesAction;
use App\Application\Action\Package\RedirectPackageAction;
use App\Application\Action\Package\RedirectPackageBadgeAction;
use App\Application\Action\Package\ViewPackageAction;
use App\Application\Action\Package\ViewPackageBadgeAction;
use App\Application\Action\System\HealthAction;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return static function (App $app): void {
  $app->options('/{routes:.*}', function (ServerRequestInterface $request, ResponseInterface $response) {
    // CORS Pre-Flight OPTIONS Request Handler
    return $response;
  });

  $app->get('/', ListPackagesAction::class)
    ->setName('listPackages');

  $app->get('/health', HealthAction::class);

  $app->group('/packages', function (Group $group) {
    $group
      ->get('', RedirectListPackagesAction::class);
    $group
      ->get('/{vendor}/{project}/status.svg', RedirectPackageBadgeAction::class)
      ->setName('redirectPackageBadge');
    $group
      ->get('/{vendor}/{project}/{version}/status.svg', ViewPackageBadgeAction::class)
      ->setName('viewPackageBadge');
    $group
      ->get('/{vendor}/{project}', RedirectPackageAction::class)
      ->setName('redirectPackage');
    $group
      ->get('/{vendor}/{project}/{version}', ViewPackageAction::class)
      ->setName('viewPackage');
  });
};
