<?php
declare(strict_types = 1);

use App\Application\Settings\SettingsInterface;
use Buzz\Browser;
use Buzz\Client\FileGetContents;
use Composer\Semver\VersionParser;
use DI\ContainerBuilder;
use Evenement\EventEmitter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use PUGX\Poser\Poser;
use PUGX\Poser\Render\SvgFlatRender;
use PUGX\Poser\Render\SvgFlatSquareRender;
use PUGX\Poser\Render\SvgPlasticRender;
use Slim\HttpCache\CacheProvider;
use Slim\Views\Twig;

return function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions(
    [
      Browser::class => function (ContainerInterface $container): Browser {
        return new Browser(
          new FileGetContents(new Psr17Factory()),
          new Psr17Factory()
        );
      },
      CacheProvider::class => \DI\autowire(CacheProvider::class),
      EventEmitter::class => \DI\autowire(EventEmitter::class),
      LoggerInterface::class => function (ContainerInterface $container): LoggerInterface {
        $settings = $container->get(SettingsInterface::class);

        $loggerSettings = $settings->get('logger');
        $logger = new Logger($loggerSettings['name']);

        $processor = new UidProcessor();
        $logger->pushProcessor($processor);

        $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
        $logger->pushHandler($handler);

        return $logger;
      },
      PDO::class => function (ContainerInterface $container): PDO {
        $settings = $container->get(SettingsInterface::class);
        $dsn = parse_url($settings->get('db'));

        return new PDO(
          sprintf(
            '%s:host=%s;port=%d;dbname=%s;user=%s;password=%s',
            $dsn['scheme'] ?? 'pgsql',
            $dsn['host'] ?? 'localhost',
            $dsn['port'] ?? 5432,
            ltrim($dsn['path'] ?? 'postgres', '/'),
            $dsn['user'] ?? 'postgres',
            $dsn['pass'] ?? '',
          ),
          options: [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
          ]
        );
      },
      Poser::class => function (ContainerInterface $container): Poser {
        return new Poser(
          [
            $container->get(SvgFlatRender::class),
            $container->get(SvgFlatSquareRender::class),
            $container->get(SvgPlasticRender::class)
          ]
        );
      },
      SvgFlatRender::class => \DI\autowire(SvgFlatRender::class),
      SvgFlatSquareRender::class => \DI\autowire(SvgFlatSquareRender::class),
      SvgPlasticRender::class => \DI\autowire(SvgPlasticRender::class),
      VersionParser::class => \DI\autowire(VersionParser::class),
      Twig::class => function (ContainerInterface $container): Twig {
        $settings = $container->get(SettingsInterface::class);
        $cache = false;
        if (isset($_ENV['PHP_ENV']) && $_ENV['PHP_ENV'] === 'production') {
          $cache = __DIR__ . '/../var/cache';
        }

        return Twig::create(
          __DIR__ . '/../resources/views',
          [
            'cache' => $cache
          ]
        );
      }
    ]
  );
};
