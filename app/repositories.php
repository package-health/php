<?php
declare(strict_types = 1);

use DI\ContainerBuilder;
use PackageHealth\PHP\Domain\Dependency\DependencyRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Preference\PreferenceRepositoryInterface;
use PackageHealth\PHP\Domain\Stats\StatsRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use PackageHealth\PHP\Infrastructure\Persistence\Dependency\CachedDependencyRepository;
use PackageHealth\PHP\Infrastructure\Persistence\Dependency\PdoDependencyRepository;
use PackageHealth\PHP\Infrastructure\Persistence\Package\CachedPackageRepository;
use PackageHealth\PHP\Infrastructure\Persistence\Package\PdoPackageRepository;
use PackageHealth\PHP\Infrastructure\Persistence\Preference\CachedPreferenceRepository;
use PackageHealth\PHP\Infrastructure\Persistence\Preference\PdoPreferenceRepository;
use PackageHealth\PHP\Infrastructure\Persistence\Stats\CachedStatsRepository;
use PackageHealth\PHP\Infrastructure\Persistence\Stats\PdoStatsRepository;
use PackageHealth\PHP\Infrastructure\Persistence\Version\CachedVersionRepository;
use PackageHealth\PHP\Infrastructure\Persistence\Version\PdoVersionRepository;
use League\Config\ConfigurationInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions(
    [
      // Dependency
      PdoDependencyRepository::class => autowire(PdoDependencyRepository::class),
      DependencyRepositoryInterface::class => static function (ContainerInterface $container): DependencyRepositoryInterface {
        /** @var \League\Config\ConfigurationInterface */
        $config = $container->get(ConfigurationInterface::class);

        if ((bool)$config->get('cache.enabled') === false) {
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
        /** @var \League\Config\ConfigurationInterface */
        $config = $container->get(ConfigurationInterface::class);

        if ((bool)$config->get('cache.enabled') === false) {
          return $container->get(PdoPackageRepository::class);
        }

        return new CachedPackageRepository(
          $container->get(PdoPackageRepository::class),
          $container->get(CacheItemPoolInterface::class)
        );
      },
      // Preference
      PdoPreferenceRepository::class => autowire(PdoPreferenceRepository::class),
      PreferenceRepositoryInterface::class => static function (ContainerInterface $container): PreferenceRepositoryInterface {
        /** @var \League\Config\ConfigurationInterface */
        $config = $container->get(ConfigurationInterface::class);

        if ((bool)$config->get('cache.enabled') === false) {
          return $container->get(PdoPreferenceRepository::class);
        }

        return new CachedPreferenceRepository(
          $container->get(PdoPreferenceRepository::class),
          $container->get(CacheItemPoolInterface::class)
        );
      },
      // Stats
      PdoStatsRepository::class => autowire(PdoStatsRepository::class),
      StatsRepositoryInterface::class => static function (ContainerInterface $container): StatsRepositoryInterface {
        /** @var \League\Config\ConfigurationInterface */
        $config = $container->get(ConfigurationInterface::class);

        if ((bool)$config->get('cache.enabled') === false) {
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
        /** @var \League\Config\ConfigurationInterface */
        $config = $container->get(ConfigurationInterface::class);

        if ((bool)$config->get('cache.enabled') === false) {
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
