<?php
declare(strict_types = 1);

use App\Application\Message\Command\PackageDiscoveryCommand;
use App\Application\Message\Command\UpdateDependencyStatusCommand;
use App\Application\Message\Command\UpdateVersionStatusCommand;
use App\Application\Message\Event\Dependency\DependencyUpdatedEvent;
use App\Application\Message\Event\Package\PackageCreatedEvent;
use App\Application\Message\Event\Package\PackageUpdatedEvent;
use App\Application\Message\Event\Version\VersionCreatedEvent;
use App\Application\Processor\Handler\PackageDiscoveryHandler;
use App\Application\Processor\Handler\UpdateDependencyStatusHandler;
use App\Application\Processor\Handler\UpdateVersionStatusHandler;
use App\Application\Processor\Listener\Dependency\DependencyUpdatedListener;
use App\Application\Processor\Listener\Package\PackageCreatedListener;
use App\Application\Processor\Listener\Package\PackageUpdatedListener;
use App\Application\Processor\Listener\Version\VersionCreatedListener;
use Courier\Bus;
use Psr\Container\ContainerInterface;

return static function (ContainerInterface $container): void {
  $bus    = $container->get(Bus::class);
  $router = $bus->getRouter();

  /* DEPENDENCY EVENTS */
  $router
    ->addRoute(
      DependencyUpdatedEvent::class,
      DependencyUpdatedListener::class,
      'DependencyUpdated'
    );

  /* PACKAGE EVENTS */
  $router
    ->addRoute(
      PackageCreatedEvent::class,
      PackageCreatedListener::class,
      'PackageCreated'
    )
    ->addRoute(
      PackageUpdatedEvent::class,
      PackageUpdatedListener::class,
      'PackageUpdated'
    );

  /* VERSION EVENTS */
  $router
    ->addRoute(
      VersionCreatedEvent::class,
      VersionCreatedListener::class,
      'VersionCreated'
    );

  /* PACKAGE COMMANDS */
  $router->addRoute(
    PackageDiscoveryCommand::class,
    PackageDiscoveryHandler::class,
    'PackageDiscovery'
  );

  /* DEPENDENCY COMMANDS */
  $router->addRoute(
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
