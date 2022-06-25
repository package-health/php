<?php
declare(strict_types = 1);

use DI\ContainerBuilder;
use PackageHealth\PHP\Application\Handler\HttpErrorHandler;
use PackageHealth\PHP\Application\Handler\ShutdownHandler;
use PackageHealth\PHP\Application\ResponseEmitter\ResponseEmitter;
use PackageHealth\PHP\Application\Settings\SettingsInterface;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

require __DIR__ . '/../vendor/autoload.php';

if (is_file(__DIR__ . '/../.env')) {
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
  $dotenv->safeLoad();
}

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

if (isset($_ENV['PHP_ENV']) && $_ENV['PHP_ENV'] === 'prod') {
  $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

// Set up settings
$settings = require __DIR__ . '/../app/settings.php';
$settings($containerBuilder);

// Set up dependencies
$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);

// Set up repositories
$repositories = require __DIR__ . '/../app/repositories.php';
$repositories($containerBuilder);

// Set up processors (handlers and listeners)
$processors = require __DIR__ . '/../app/processors.php';
$processors($containerBuilder);

// Set up services
$services = require __DIR__ . '/../app/services.php';
$services($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();

// Register messages (commands and events)
$messages = require __DIR__ . '/../app/messages.php';
$messages($container);

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();

// Register middleware
$middleware = require __DIR__ . '/../app/middleware.php';
$middleware($app);

// Register routes
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

if (isset($_ENV['PHP_ENV']) && $_ENV['PHP_ENV'] === 'prod') {
  $routeCollector = $app->getRouteCollector();
  $routeCollector->setCacheFile(__DIR__ . '/../var/cache/routes.cache');
}

/** @var SettingsInterface $settings */
$settings = $container->get(SettingsInterface::class);

$displayErrorDetails = $settings->getBool('displayErrorDetails');

// Create Request object from globals
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// Create Error Handler
$errorHandler = new HttpErrorHandler($app->getCallableResolver(), $app->getResponseFactory());

// Create Shutdown Handler
$shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
register_shutdown_function($shutdownHandler);

// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(
  $displayErrorDetails,
  $settings->getBool('logError'),
  $settings->getBool('logErrorDetails')
);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Run App & Emit Response
$response = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);
