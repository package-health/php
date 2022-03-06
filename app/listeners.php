<?php
declare(strict_types = 1);

use App\Application\Listeners\DependencyListener;
use App\Application\Listeners\PackageListener;
use App\Application\Listeners\StatsListener;
use App\Application\Listeners\VersionListener;
use DI\ContainerBuilder;
use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions([
    DependencyListener::class => autowire(DependencyListener::class),
    PackageListener::class    => autowire(PackageListener::class),
    StatsListener::class      => autowire(StatsListener::class),
    VersionListener::class    => autowire(VersionListener::class)
  ]);
};
