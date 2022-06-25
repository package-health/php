<?php
declare(strict_types = 1);

use DI\ContainerBuilder;
use PackageHealth\PHP\Application\Processor\Handler\PackageDiscoveryHandler;
use PackageHealth\PHP\Application\Processor\Handler\UpdateDependencyStatusHandler;
use PackageHealth\PHP\Application\Processor\Handler\UpdateVersionStatusHandler;
use PackageHealth\PHP\Application\Processor\Listener\Dependency\DependencyUpdatedListener;
use PackageHealth\PHP\Application\Processor\Listener\Package\PackageUpdatedListener;
use PackageHealth\PHP\Application\Processor\Listener\Version\VersionCreatedListener;
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
      DependencyUpdatedListener::class => autowire(DependencyUpdatedListener::class),
      PackageUpdatedListener::class    => autowire(PackageUpdatedListener::class),
      VersionCreatedListener::class    => autowire(VersionCreatedListener::class),
    ]
  );
};
