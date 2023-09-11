<?php
declare(strict_types = 1);

use Composer\InstalledVersions;
use DI\ContainerBuilder;
use PackageHealth\PHP\Application\Handler\HttpErrorHandler;
use PackageHealth\PHP\Application\Handler\ShutdownHandler;
use PackageHealth\PHP\Application\ResponseEmitter\ResponseEmitter;
use League\Config\ConfigurationInterface;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

require __DIR__ . '/../vendor/autoload.php';

if (is_file(__DIR__ . '/../.env')) {
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
  $dotenv->safeLoad();
}

$version = $_ENV['VERSION'] ?? '';
if ($version === '') {
  $version = InstalledVersions::getVersion('package-health/php');
  if (str_starts_with(InstalledVersions::getPrettyVersion('package-health/php'), 'dev-')) {
    $version = sprintf(
      '%s-%s',
      substr(InstalledVersions::getPrettyVersion('package-health/php'), 4),
      substr(InstalledVersions::getReference('package-health/php'), 0, 7)
    );
  }
}

define('__VERSION__', $version);

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

if (isset($_ENV['PHP_ENV']) && $_ENV['PHP_ENV'] === 'prod') {
  $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

// Set up settings
$settings = require_once __DIR__ . '/../app/settings.php';
$settings($containerBuilder);

// Set up dependencies
$dependencies = require_once __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);

// Set up repositories
$repositories = require_once __DIR__ . '/../app/repositories.php';
$repositories($containerBuilder);

// Set up processors (handlers and listeners)
$processors = require_once __DIR__ . '/../app/processors.php';
$processors($containerBuilder);

// Set up services
$services = require_once __DIR__ . '/../app/services.php';
$services($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();

// Register messages (commands and events)
$messages = require_once __DIR__ . '/../app/messages.php';
$messages($container);

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();

// Register middleware
$middleware = require_once __DIR__ . '/../app/middleware.php';
$middleware($app);

// Register routes
$routes = require_once __DIR__ . '/../app/routes.php';
$routes($app);

if (isset($_ENV['PHP_ENV']) && $_ENV['PHP_ENV'] === 'prod') {
  $routeCollector = $app->getRouteCollector();
  $routeCollector->setCacheFile(__DIR__ . '/../var/cache/routes.cache');
}

/** @var \League\Config\ConfigurationInterface $settings */
$config = $container->get(ConfigurationInterface::class);

$displayErrorDetails = (bool)$config->get('slim.displayErrorDetails');

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
  (bool)$config->get('slim.logErrors'),
  (bool)$config->get('slim.logErrorDetails')
);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Run App & Emit Response
$response = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);
