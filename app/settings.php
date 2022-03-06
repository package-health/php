<?php
declare(strict_types = 1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;

return static function (ContainerBuilder $containerBuilder): void {
  // Global Settings Object
  $containerBuilder->addDefinitions([
    SettingsInterface::class => function () {
      return new Settings([
        'db' => "pgsql://${_ENV['POSTGRES_USER']}:${_ENV['POSTGRES_PASSWORD']}@${_ENV['POSTGRES_HOST']}/${_ENV['POSTGRES_DB']}",
        'displayErrorDetails' => (isset($_ENV['PHP_ENV']) === false || $_ENV['PHP_ENV'] === 'development'),
        'logError'            => true,
        'logErrorDetails'     => true,
        'logger' => [
          'name' => 'slim-app',
          'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
          'level' => Logger::DEBUG,
        ],
      ]);
    }
  ]);
};
