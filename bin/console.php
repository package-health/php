#!/usr/bin/env php
<?php
declare(strict_types = 1);

date_default_timezone_set('UTC');
setlocale(LC_ALL, 'en_US.UTF8');

// ensure correct absolute path
chdir(dirname($argv[0]));

require_once __DIR__ . '/../vendor/autoload.php';

use App\Application\Console\Package\GetDataCommand;
use App\Application\Console\Package\GetListCommand;
use App\Application\Console\Package\GetUpdatesCommand;
use App\Application\Console\Package\MassImportCommand;
use DI\ContainerBuilder;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;


if (is_file(__DIR__ . '/../.env')) {
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
  $dotenv->safeLoad();
}

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

if (isset($_ENV['PHP_ENV']) && $_ENV['PHP_ENV'] === 'production') {
  // workaround for https://github.com/PHP-DI/PHP-DI/issues/791
  if (PHP_VERSION_ID < 80100) {
    $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
  }
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

// Set up console commands
$console = require __DIR__ . '/../app/console.php';
$console($containerBuilder);

// Set up listeners
$listeners = require __DIR__ . '/../app/listeners.php';
$listeners($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();

// Register events
$events = require __DIR__ . '/../app/events.php';
$events($container);

$app = new Application('php.package.health console');

$commandLoader = new FactoryCommandLoader(
  [
    GetDataCommand::getDefaultName() => static function () use ($container): GetDataCommand {
      return $container->get(GetDataCommand::class);
    },
    GetListCommand::getDefaultName() => static function () use ($container): GetListCommand {
      return $container->get(GetListCommand::class);
    },
    GetUpdatesCommand::getDefaultName() => static function () use ($container): GetUpdatesCommand {
      return $container->get(GetUpdatesCommand::class);
    },
    MassImportCommand::getDefaultName() => static function () use ($container): MassImportCommand {
      return $container->get(MassImportCommand::class);
    }
  ]
);

$app->setCommandLoader($commandLoader);
$app->run();
