<?php
declare(strict_types = 1);

use Slim\App;
use Slim\HttpCache\Cache;
use Slim\Middleware\ContentLengthMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app): void {
  $container = $app->getContainer();

  $app->add(new ContentLengthMiddleware());
  $app->add(new Cache('public', 86400));
  $app->add(TwigMiddleware::create($app, $container->get(Twig::class)));
};
