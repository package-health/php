#!/usr/bin/env php
<?php
declare(strict_types = 1);

date_default_timezone_set('UTC');
setlocale(LC_ALL, 'en_US.UTF8');

// ensure correct absolute path
chdir(dirname($argv[0]));

require_once __DIR__ . '/../vendor/autoload.php';

use App\Application\Console\Packagist\GetDataCommand;
use App\Application\Console\Packagist\GetListCommand;
use App\Application\Console\Packagist\GetUpdatesCommand;
use App\Application\Console\Packagist\MassImportCommand;
use App\Application\Console\Queue\QueueConsumerCommand;
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

// Set up processors (handlers and listeners)
$processors = require __DIR__ . '/../app/processors.php';
$processors($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();

// Register messages (commands and events)
$messages = require __DIR__ . '/../app/messages.php';
$messages($container);

$app = new Application('php.package.health console');
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
      QueueConsumerCommand::getDefaultName() => static function () use ($container): QueueConsumerCommand {
        return $container->get(QueueConsumerCommand::class);
      }
    ]
  )
);

$app->run();
