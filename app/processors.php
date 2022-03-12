<?php
declare(strict_types = 1);

use App\Application\Processor\Handler\PackageDiscoveryHandler;
use App\Application\Processor\Handler\UpdateDependencyStatusHandler;
use App\Application\Processor\Handler\UpdateVersionStatusHandler;
use App\Application\Processor\Listener\Dependency\DependencyCreatedListener;
use App\Application\Processor\Listener\Dependency\DependencyUpdatedListener;
use App\Application\Processor\Listener\Package\PackageCreatedListener;
use App\Application\Processor\Listener\Package\PackageUpdatedListener;
use App\Application\Processor\Listener\Version\VersionCreatedListener;
use App\Application\Processor\Listener\Version\VersionUpdatedListener;
use DI\ContainerBuilder;
use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
  /* HANDLERS */
  $containerBuilder->addDefinitions(
    [
      PackageDiscoveryHandler::class => autowire(PackageDiscoveryHandler::class),
      UpdateDependencyStatusHandler::class => autowire(UpdateDependencyStatusHandler::class),
      UpdateVersionStatusHandler::class => autowire(UpdateVersionStatusHandler::class)
    ]
  );

  /* LISTENERS */
  $containerBuilder->addDefinitions(
    [
      DependencyCreatedListener::class => autowire(DependencyCreatedListener::class),
      DependencyUpdatedListener::class => autowire(DependencyUpdatedListener::class),
      PackageCreatedListener::class    => autowire(PackageCreatedListener::class),
      PackageUpdatedListener::class    => autowire(PackageUpdatedListener::class),
      VersionCreatedListener::class    => autowire(VersionCreatedListener::class),
      VersionUpdatedListener::class    => autowire(VersionUpdatedListener::class)
    ]
  );
};
