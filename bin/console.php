#!/usr/bin/env php
<?php
declare(strict_types = 1);

date_default_timezone_set('UTC');
setlocale(LC_ALL, 'en_US.UTF8');

// ensure correct absolute path
chdir(dirname($argv[0]));

require_once __DIR__ . '/../vendor/autoload.php';

use Composer\InstalledVersions;
use DI\ContainerBuilder;
use PackageHealth\PHP\Application\Console\Packagist\GetListCommand;
use PackageHealth\PHP\Application\Console\Packagist\GetPackageCommand;
use PackageHealth\PHP\Application\Console\Packagist\GetUpdatesCommand;
use PackageHealth\PHP\Application\Console\Packagist\MassImportCommand;
use PackageHealth\PHP\Application\Console\Queue\ConsumeCommand;
use PackageHealth\PHP\Application\Console\Queue\ListCommand;
use PackageHealth\PHP\Application\Console\Queue\SendCommandCommand;
use PackageHealth\PHP\Application\Console\Queue\SendEventCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;

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

// Set up console commands
$console = require_once __DIR__ . '/../app/console.php';
$console($containerBuilder);

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

$app = new Application('php.package.health console', __VERSION__);
$app->setCommandLoader(
  new FactoryCommandLoader(
    [
      GetListCommand::getDefaultName() => static function () use ($container): GetListCommand {
        return $container->get(GetListCommand::class);
      },
      GetPackageCommand::getDefaultName() => static function () use ($container): GetPackageCommand {
        return $container->get(GetPackageCommand::class);
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
      },
      SendCommandCommand::getDefaultName() => static function () use ($container): SendCommandCommand {
        return $container->get(SendCommandCommand::class);
      },
      SendEventCommand::getDefaultName() => static function () use ($container): SendEventCommand {
        return $container->get(SendEventCommand::class);
      }
    ]
  )
);

$app->run();
