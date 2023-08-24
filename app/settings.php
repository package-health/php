<?php
declare(strict_types = 1);

use DI\ContainerBuilder;
use PackageHealth\PHP\Application\Settings\Settings;
use PackageHealth\PHP\Application\Settings\SettingsInterface;
use Psr\Log\LogLevel;

return static function (ContainerBuilder $containerBuilder): void {
  // Global Settings Object
  $containerBuilder->addDefinitions(
    [
      SettingsInterface::class => static function (): SettingsInterface {
        return new Settings(
          [
            'cache' => [
              'enabled' => PHP_SAPI !== 'cli',
              'redis' => [
                'enabled' => extension_loaded('redis') === true,
                'dsn' => sprintf(
                  'redis://%s:%s@%s:%d',
                  $_ENV['REDIS_USERNAME'],
                  $_ENV['REDIS_PASSWORD'],
                  $_ENV['REDIS_HOST'],
                  $_ENV['REDIS_PORT'] ?? 6379
                )
              ]
            ],
            'db' => [
              'dsn' => sprintf(
                'pgsql://%s:%s@%s:%d/%s',
                $_ENV['POSTGRES_USER'],
                $_ENV['POSTGRES_PASSWORD'],
                $_ENV['POSTGRES_HOST'],
                $_ENV['POSTGRES_PORT'] ?? 5432,
                $_ENV['POSTGRES_DB']
              )
            ],
            'queue' => [
              'dsn' => sprintf(
                'amqp://%s:%s@%s:%d',
                $_ENV['AMQP_USER'],
                $_ENV['AMQP_PASS'],
                $_ENV['AMQP_HOST'],
                $_ENV['AMQP_PORT'] ?? 5672
              ),
              'prefetch' => 100
            ],
            'displayErrorDetails' => (isset($_ENV['PHP_ENV']) === false || $_ENV['PHP_ENV'] === 'dev'),
            'logError'            => true,
            'logErrorDetails'     => true,
            'logger' => [
              'name' => 'slim-app',
              'path' => isset($_ENV['DOCKER']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
              'level' => isset($_ENV['PHP_ENV']) === false || $_ENV['PHP_ENV'] === 'dev' ? LogLevel::DEBUG : LogLevel::INFO
            ]
          ]
        );
      }
    ]
  );
};
