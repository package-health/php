<?php
declare(strict_types = 1);

use Buzz\Browser;
use Buzz\Client\Curl;
use Composer\Semver\VersionParser;
use Courier\Bus;
use Courier\Client\Consumer;
use Courier\Client\Producer;
use Courier\Inflector\InterfaceInflector;
use Courier\Locator\ContainerLocator;
use Courier\Middleware\EnvelopeCompressionMiddleware;
use Courier\Middleware\EnvelopeTimestampMiddleware;
use Courier\Middleware\PersistentDeliveryMiddleware;
use Courier\Router\SimpleRouter;
use Courier\Serializer\IgBinarySerializer;
use Courier\Transport\AmqpTransport;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Nyholm\Dsn\DsnParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PackageHealth\PHP\Application\Settings\SettingsInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use PUGX\Poser\Poser;
use PUGX\Poser\Render\SvgFlatRender;
use PUGX\Poser\Render\SvgFlatSquareRender;
use PUGX\Poser\Render\SvgPlasticRender;
use Slim\HttpCache\CacheProvider;
use Slim\Views\Twig;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

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

        $amqp = AmqpTransport::fromDsn($settings->getString('queue.dsn'));
        $amqp->setPrefetchCount($settings->getInt('queue.prefetch', 25));

        return new Bus(
          new SimpleRouter(),
          $amqp
        );
      },
      CacheItemPoolInterface::class => function (ContainerInterface $container): CacheItemPoolInterface {
        $settings = $container->get(SettingsInterface::class);

        // disables cache by using a black hole driver
        if ($settings->has('cache') === false || $settings->getBool('cache.enabled', false) === false) {
          return new ArrayAdapter(
            // the default lifetime (in seconds) for cache items that do not define their
            // own lifetime, with a value 0 causing items to be stored indefinitely (i.e.
            // until the current PHP process finishes)
            defaultLifetime: 0,

            // if ``true``, the values saved in the cache are serialized before storing them
            storeSerialized: true,

            // the maximum lifetime (in seconds) of the entire cache (after this time, the
            // entire cache is deleted to avoid stale data from consuming memory)
            maxLifetime: 0,

            // the maximum number of items that can be stored in the cache. When the limit
            // is reached, cache follows the LRU model (least recently used items are deleted)
            maxItems: 0
          );
        }

        $adapters = [
          new ArrayAdapter()
        ];

        if ($settings->has('cache.apcu') && $settings->getBool('cache.apcu.enabled', false)) {
          $adapters[] = new ApcuAdapter();
        }

        if ($settings->has('cache.redis') && $settings->getBool('cache.redis.enabled', false)) {
          $adapters[] = new RedisAdapter(
            RedisAdapter::createConnection($settings->getString('cache.redis.dsn'))
          );
        }

        return new ChainAdapter($adapters);
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
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
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
      Producer::class => function (ContainerInterface $container): Producer {
        $producer = new Producer(
          $container->get(Bus::class),
          new IgBinarySerializer()
        );

        $producer
          ->addMiddleware(new EnvelopeTimestampMiddleware())
          ->addMiddleware(new EnvelopeCompressionMiddleware())
          ->addMiddleware(new PersistentDeliveryMiddleware());

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
