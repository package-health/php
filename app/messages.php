<?php
declare(strict_types = 1);

use Courier\Bus;
use PackageHealth\PHP\Application\Message\Command\CheckDependencyStatusCommand;
use PackageHealth\PHP\Application\Message\Command\PackageDiscoveryCommand;
use PackageHealth\PHP\Application\Message\Command\PackagePurgeCommand;
use PackageHealth\PHP\Application\Message\Command\UpdateDependencyStatusCommand;
use PackageHealth\PHP\Application\Message\Command\UpdateVersionStatusCommand;
use PackageHealth\PHP\Application\Message\Event\Dependency\DependencyCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Dependency\DependencyUpdatedEvent;
use PackageHealth\PHP\Application\Message\Event\Package\PackageCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Package\PackageUpdatedEvent;
use PackageHealth\PHP\Application\Message\Event\Version\VersionCreatedEvent;
use PackageHealth\PHP\Application\Processor\Handler\CheckDependencyStatusHandler;
use PackageHealth\PHP\Application\Processor\Handler\PackageDiscoveryHandler;
use PackageHealth\PHP\Application\Processor\Handler\PackagePurgeHandler;
use PackageHealth\PHP\Application\Processor\Handler\UpdateDependencyStatusHandler;
use PackageHealth\PHP\Application\Processor\Handler\UpdateVersionStatusHandler;
use PackageHealth\PHP\Application\Processor\Listener\Dependency\DependencyCreatedListener;
use PackageHealth\PHP\Application\Processor\Listener\Dependency\DependencyUpdatedListener;
use PackageHealth\PHP\Application\Processor\Listener\Package\PackageCreatedListener;
use PackageHealth\PHP\Application\Processor\Listener\Package\PackageUpdatedListener;
use PackageHealth\PHP\Application\Processor\Listener\Version\VersionCreatedListener;
use Psr\Container\ContainerInterface;

return static function (ContainerInterface $container): void {
  $bus    = $container->get(Bus::class);
  $router = $bus->getRouter();

  /* DEPENDENCY EVENTS */
  $router
    ->addRoute(
      DependencyCreatedEvent::class,
      DependencyCreatedListener::class,
      'DependencyCreated'
    )
    ->addRoute(
      DependencyUpdatedEvent::class,
      DependencyUpdatedListener::class,
      'DependencyUpdated'
    );

  /* PACKAGE EVENTS */
  $router->addRoute(
    PackageUpdatedEvent::class,
    PackageUpdatedListener::class,
    'PackageUpdated'
  );

  /* VERSION EVENTS */
  $router->addRoute(
    VersionCreatedEvent::class,
    VersionCreatedListener::class,
    'VersionCreated'
  );

  /* PACKAGE COMMANDS */
  $router
    ->addRoute(
      PackageDiscoveryCommand::class,
      PackageDiscoveryHandler::class,
      'PackageDiscovery'
    )
    ->addRoute(
      PackagePurgeCommand::class,
      PackagePurgeHandler::class,
      'PackagePurge'
    );

  /* DEPENDENCY COMMANDS */
  $router
    ->addRoute(
      CheckDependencyStatusCommand::class,
      CheckDependencyStatusHandler::class,
      'CheckDependencyStatus'
    )
    ->addRoute(
      UpdateDependencyStatusCommand::class,
      UpdateDependencyStatusHandler::class,
      'UpdateDependencyStatus'
    );

  /* VERSION COMMANDS */
  $router->addRoute(
    UpdateVersionStatusCommand::class,
    UpdateVersionStatusHandler::class,
    'UpdateVersionStatus'
  );

  $bus->bindRoutes();
};
