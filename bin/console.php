#!/usr/bin/env php
<?php
declare(strict_types = 1);

date_default_timezone_set('UTC');
setlocale(LC_ALL, 'en_US.UTF8');

// ensure correct absolute path
chdir(dirname($argv[0]));

require_once __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use PackageHealth\PHP\Application\Console\Packagist\GetDataCommand;
use PackageHealth\PHP\Application\Console\Packagist\GetListCommand;
use PackageHealth\PHP\Application\Console\Packagist\GetUpdatesCommand;
use PackageHealth\PHP\Application\Console\Packagist\MassImportCommand;
use PackageHealth\PHP\Application\Console\Queue\ConsumeCommand;
use PackageHealth\PHP\Application\Console\Queue\ListCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;

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

// Set up console commands
$console = require __DIR__ . '/../app/console.php';
$console($containerBuilder);

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

$app = new Application('php.package.health console', $_ENV['VERSION'] ?? '');
$app->setCommandLoader(
  new FactoryCommandLoader(
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
      },
      ConsumeCommand::getDefaultName() => static function () use ($container): ConsumeCommand {
        return $container->get(ConsumeCommand::class);
      },
      ListCommand::getDefaultName() => static function () use ($container): ListCommand {
        return $container->get(ListCommand::class);
      }
    ]
  )
);

$app->run();
