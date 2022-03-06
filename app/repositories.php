<?php
declare(strict_types = 1);

use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Package\PackageRepositoryInterface;
use App\Domain\Stats\StatsRepositoryInterface;
use App\Domain\Version\VersionRepositoryInterface;
use App\Infrastructure\Persistence\Dependency\PdoDependencyRepository;
use App\Infrastructure\Persistence\Package\PdoPackageRepository;
use App\Infrastructure\Persistence\Stats\PdoStatsRepository;
use App\Infrastructure\Persistence\Version\PdoVersionRepository;
use DI\ContainerBuilder;
use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions([
    DependencyRepositoryInterface::class => autowire(PdoDependencyRepository::class),
    PackageRepositoryInterface::class    => autowire(PdoPackageRepository::class),
    StatsRepositoryInterface::class      => autowire(PdoStatsRepository::class),
    VersionRepositoryInterface::class    => autowire(PdoVersionRepository::class)
  ]);
};
