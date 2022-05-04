<?php
declare(strict_types = 1);

use Middlewares\Minifier;
use Middlewares\TrailingSlash;
use Slim\App;
use Slim\HttpCache\Cache;
use Slim\Middleware\ContentLengthMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return static function (App $app): void {
  $container = $app->getContainer();

  $app
    ->add(Minifier::html())
    ->add(new ContentLengthMiddleware())
    ->add(new Cache('public', 86400))
    ->add((new TrailingSlash(false))->redirect())
    ->add(TwigMiddleware::create($app, $container->get(Twig::class)));
};
