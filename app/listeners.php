<?php
declare(strict_types = 1);

use App\Application\Listeners\DependencyListener;
use App\Application\Listeners\PackageListener;
use App\Application\Listeners\StatsListener;
use App\Application\Listeners\VersionListener;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions([
    DependencyListener::class => \DI\autowire(DependencyListener::class),
    PackageListener::class    => \DI\autowire(PackageListener::class),
    StatsListener::class      => \DI\autowire(StatsListener::class),
    VersionListener::class    => \DI\autowire(VersionListener::class)
  ]);
};
