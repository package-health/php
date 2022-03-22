<?php
declare(strict_types = 1);

use App\Application\Settings\SettingsInterface;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Package\PackageRepositoryInterface;
use App\Domain\Stats\StatsRepositoryInterface;
use App\Domain\Version\VersionRepositoryInterface;
use App\Infrastructure\Persistence\Dependency\CachedDependencyRepository;
use App\Infrastructure\Persistence\Dependency\PdoDependencyRepository;
use App\Infrastructure\Persistence\Package\CachedPackageRepository;
use App\Infrastructure\Persistence\Package\PdoPackageRepository;
use App\Infrastructure\Persistence\Stats\CachedStatsRepository;
use App\Infrastructure\Persistence\Stats\PdoStatsRepository;
use App\Infrastructure\Persistence\Version\CachedVersionRepository;
use App\Infrastructure\Persistence\Version\PdoVersionRepository;
use DI\ContainerBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions(
    [
      // Dependency
      PdoDependencyRepository::class => autowire(PdoDependencyRepository::class),
      DependencyRepositoryInterface::class => static function (ContainerInterface $container): DependencyRepositoryInterface {
        $settings = $container->get(SettingsInterface::class);

        if ($settings->has('cache') === false || $settings->getBool('cache.enabled', false) === false) {
          return $container->get(PdoDependencyRepository::class);
        }

        return new CachedDependencyRepository(
          $container->get(PdoDependencyRepository::class),
          $container->get(CacheItemPoolInterface::class)
        );
      },
      // Package
      PdoPackageRepository::class => autowire(PdoPackageRepository::class),
      PackageRepositoryInterface::class => static function (ContainerInterface $container): PackageRepositoryInterface {
        $settings = $container->get(SettingsInterface::class);

        if ($settings->has('cache') === false || $settings->getBool('cache.enabled', false) === false) {
          return $container->get(PdoPackageRepository::class);
        }

        return new CachedPackageRepository(
          $container->get(PdoPackageRepository::class),
          $container->get(CacheItemPoolInterface::class)
        );
      },
      // Stats
      PdoStatsRepository::class => autowire(PdoStatsRepository::class),
      StatsRepositoryInterface::class => static function (ContainerInterface $container): StatsRepositoryInterface {
        $settings = $container->get(SettingsInterface::class);

        if ($settings->has('cache') === false || $settings->getBool('cache.enabled', false) === false) {
          return $container->get(PdoStatsRepository::class);
        }

        return new CachedStatsRepository(
          $container->get(PdoStatsRepository::class),
          $container->get(CacheItemPoolInterface::class)
        );
      },
      // Version
      PdoVersionRepository::class => autowire(PdoVersionRepository::class),
      VersionRepositoryInterface::class => static function (ContainerInterface $container): VersionRepositoryInterface {
        $settings = $container->get(SettingsInterface::class);

        if ($settings->has('cache') === false || $settings->getBool('cache.enabled', false) === false) {
          return $container->get(PdoVersionRepository::class);
        }

        return new CachedVersionRepository(
          $container->get(PdoVersionRepository::class),
          $container->get(CacheItemPoolInterface::class)
        );
      }
    ]
  );
};
