<?php
declare(strict_types = 1);

use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Package\PackageRepositoryInterface;
use App\Domain\Stats\StatsRepositoryInterface;
use App\Domain\Version\VersionRepositoryInterface;
use App\Infrastructure\Persistence\Dependency\SqlDependencyRepository;
use App\Infrastructure\Persistence\Package\SqlPackageRepository;
use App\Infrastructure\Persistence\Stats\SqlStatsRepository;
use App\Infrastructure\Persistence\Version\SqlVersionRepository;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions([
    DependencyRepositoryInterface::class => \DI\autowire(SqlDependencyRepository::class),
    PackageRepositoryInterface::class    => \DI\autowire(SqlPackageRepository::class),
    StatsRepositoryInterface::class      => \DI\autowire(SqlStatsRepository::class),
    VersionRepositoryInterface::class    => \DI\autowire(SqlVersionRepository::class)
  ]);
};
