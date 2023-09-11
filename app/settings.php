<?php
declare(strict_types = 1);

use DI\ContainerBuilder;
use League\Config\Configuration;
use League\Config\ConfigurationInterface;
use Nette\Schema\Expect;
use Psr\Log\LogLevel;

return static function (ContainerBuilder $containerBuilder): void {
  // Global Settings Object
  $containerBuilder->addDefinitions(
    [
      ConfigurationInterface::class => static function (): ConfigurationInterface {
        // config schema
        $config = new Configuration(
          [
            'cache' => Expect::structure(
              [
                'enabled' => Expect::bool(false),
                'apcu' => Expect::structure(
                  [
                    'enabled' => Expect::bool(false)
                  ]
                ),
                'redis' => Expect::structure(
                  [
                    'enabled' => Expect::bool(false),
                    'dsn' => Expect::string('redis://localhost:6379')
                  ]
                )
              ]
            ),
            'db' => Expect::structure(
              [
                'dsn' => Expect::string('pgsql://postgres@localhost:5432/postgres')
              ]
            ),
            'logging' => Expect::structure(
              [
                'enabled' => Expect::bool(false),
                'level' => Expect::string(LogLevel::INFO),
                'path' => Expect::string()->assert(
                  static function (string $file): bool {
                    return $file === 'php://stdout' || is_writeable(dirname($file));
                  }
                )
              ]
            ),
            'queue' => Expect::structure(
              [
                'dsn' => Expect::string('amqp://guest:guest@localhost:5672/'),
                'prefetch' => Expect::int(100)
              ]
            ),
            'slim' => Expect::structure(
              [
                // Returns a detailed HTML page with error details and
                // a stack trace. Should be disabled in production.
                'displayErrorDetails' => Expect::bool(false),
                // Whether to display errors on the internal PHP log or not.
                'logErrors' => Expect::bool(true),
                // If true, display full errors with message and stack trace on the PHP log.
                // If false, display only "Slim Application Error" on the PHP log.
                // Doesn't do anything when "logErrors" is false.
                'logErrorDetails' => Expect::bool(true)
              ]
            )
          ]
        );

        // actual values
        $config->merge(
          [
            'cache' => [
              'enabled' => PHP_SAPI !== 'cli',
              'apcu' => [
                'enabled' => extension_loaded('apcu') === true && apcu_enabled() && PHP_SAPI === 'fpm-fcgi'
              ],
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
            'logging' => [
              'path' => (
                isset($_ENV['DOCKER']) === true
                ? 'php://stdout'
                : dirname(__DIR__) . '/application.log'
              ),
              'level' => (
                $_ENV['PHP_ENV'] === 'prod'
                ? LogLevel::INFO
                : LogLevel::DEBUG
              )
            ],
            'slim' => [
              'displayErrorDetails' => (isset($_ENV['PHP_ENV']) === false || $_ENV['PHP_ENV'] === 'dev')
            ]
          ]
        );

        return $config->reader();
      }
    ]
  );
};
