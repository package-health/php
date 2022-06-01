<?php
declare(strict_types = 1);

use App\Application\Settings\SettingsInterface;
use Buzz\Browser;
use Buzz\Client\Curl;
use Composer\Semver\VersionParser;
use Courier\Bus;
use Courier\Client\Consumer;
use Courier\Client\Producer\BufferedProducer;
use Courier\Client\Producer\Producer;
use Courier\Client\Producer\ProducerInterface;
use Courier\Inflector\InterfaceInflector;
use Courier\Locator\ContainerLocator;
use Courier\Middleware\EnvelopeCompressionMiddleware;
use Courier\Router\SimpleRouter;
use Courier\Serializer\IgBinarySerializer;
use Courier\Transport\AmqpTransport;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Nyholm\Dsn\DsnParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use PUGX\Poser\Poser;
use PUGX\Poser\Render\SvgFlatRender;
use PUGX\Poser\Render\SvgFlatSquareRender;
use PUGX\Poser\Render\SvgPlasticRender;
use Slim\HttpCache\CacheProvider;
use Slim\Views\Twig;
use Stash\Driver\BlackHole;
use Stash\Driver\Composite;
use Stash\Driver\Ephemeral;
use Stash\Driver\FileSystem;
use Stash\Driver\Redis;
use Stash\Pool;
use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions(
    [
      Browser::class => function (ContainerInterface $container): Browser {
        return new Browser(
          new Curl(new Psr17Factory()),
          new Psr17Factory()
        );
      },
      Bus::class => function (ContainerInterface $container): Bus {
        $settings = $container->get(SettingsInterface::class);

        return new Bus(
          new SimpleRouter(),
          AmqpTransport::fromDsn($settings->getString('queue.dsn'))
        );
      },
      CacheItemPoolInterface::class => function (ContainerInterface $container): Pool {
        $settings = $container->get(SettingsInterface::class);

        // disables cache by using a black hole driver
        if ($settings->has('cache') === false || $settings->getBool('cache.enabled', false) === false) {
          return new Pool(
            new BlackHole()
          );
        }

        $drivers = [
          new Ephemeral()
        ];

        if ($settings->has('cache.redis')) {
          $dsn = DsnParser::parse($settings->getString('cache.redis'));

          $drivers[] = new Redis(
            [
              'servers' => [
                [
                  'server' => $dsn->getHost() ?? 'localhost',
                  'port'   => $dsn->getPort() ?? 6379
                ]
              ],
              'username' => $dsn->getUser() ?? null,
              'password' => $dsn->getPassword() ?? null
            ]
          );
        }

        $drivers[] = new FileSystem(
          [
            'path' => __DIR__ . '/../var/cache'
          ]
        );

        return new Pool(
          new Composite(
            [
              'drivers' => $drivers
            ]
          )
        );
      },
      CacheProvider::class => autowire(CacheProvider::class),
      Consumer::class => function (ContainerInterface $container): Consumer {
        $consumer = new Consumer(
          $container->get(Bus::class),
          new InterfaceInflector(),
          new ContainerLocator($container),
          new IgBinarySerializer()
        );

        $consumer->addMiddleware(new EnvelopeCompressionMiddleware());

        return $consumer;
      },
      LoggerInterface::class => function (ContainerInterface $container): LoggerInterface {
        $settings = $container->get(SettingsInterface::class);

        $logger = new Logger($settings->getString('logger.name'));

        $processor = new UidProcessor();
        $logger->pushProcessor($processor);

        $handler = new StreamHandler(
          $settings->getString('logger.path'),
          $settings->getString('logger.level')
        );
        $logger->pushHandler($handler);

        return $logger;
      },
      PDO::class => function (ContainerInterface $container): PDO {
        $settings = $container->get(SettingsInterface::class);
        $dsn = DsnParser::parse($settings->getString('db.dsn'));

        return new PDO(
          sprintf(
            '%s:host=%s;port=%d;dbname=%s;user=%s;password=%s',
            $dsn->getScheme() ?? 'pgsql',
            $dsn->getHost() ?? 'localhost',
            $dsn->getPort() ?? 5432,
            ltrim($dsn->getPath() ?? 'postgres', '/'),
            $dsn->getUser() ?? 'postgres',
            $dsn->getPassword() ?? '',
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
      ProducerInterface::class => function (ContainerInterface $container): ProducerInterface {
        $producer = new BufferedProducer(
          new Producer(
            $container->get(Bus::class),
            new IgBinarySerializer()
          )
        );

        $producer->addMiddleware(new EnvelopeCompressionMiddleware());

        return $producer;
      },
      SvgFlatRender::class => autowire(SvgFlatRender::class),
      SvgFlatSquareRender::class => autowire(SvgFlatSquareRender::class),
      SvgPlasticRender::class => autowire(SvgPlasticRender::class),
      VersionParser::class => autowire(VersionParser::class),
      Twig::class => function (ContainerInterface $container): Twig {
        $cache = false;
        if (isset($_ENV['PHP_ENV']) && $_ENV['PHP_ENV'] === 'prod') {
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
