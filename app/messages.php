<?php
declare(strict_types = 1);

use App\Application\Message\Command\PackageDiscoveryCommand;
use App\Application\Message\Command\UpdateDependencyStatusCommand;
use App\Application\Message\Command\UpdateVersionStatusCommand;
use App\Application\Message\Event\Dependency\DependencyCreatedEvent;
use App\Application\Message\Event\Dependency\DependencyUpdatedEvent;
use App\Application\Message\Event\Package\PackageCreatedEvent;
use App\Application\Message\Event\Package\PackageUpdatedEvent;
use App\Application\Message\Event\Version\VersionCreatedEvent;
use App\Application\Message\Event\Version\VersionUpdatedEvent;
use App\Application\Processor\Handler\PackageDiscoveryHandler;
use App\Application\Processor\Handler\UpdateDependencyStatusHandler;
use App\Application\Processor\Handler\UpdateVersionStatusHandler;
use App\Application\Processor\Listener\Dependency\DependencyCreatedListener;
use App\Application\Processor\Listener\Dependency\DependencyUpdatedListener;
use App\Application\Processor\Listener\Package\PackageCreatedListener;
use App\Application\Processor\Listener\Package\PackageUpdatedListener;
use App\Application\Processor\Listener\Version\VersionCreatedListener;
use App\Application\Processor\Listener\Version\VersionUpdatedListener;
use Courier\Bus;
use Psr\Container\ContainerInterface;

return static function (ContainerInterface $container): void {
  $router = $container->get(Bus::class)->getRouter();

  /* DEPENDENCY EVENTS */
  $router
    ->addRoute(
      DependencyCreatedEvent::class,
      DependencyCreatedListener::class
    )
    ->addRoute(
      DependencyUpdatedEvent::class,
      DependencyUpdatedListener::class
    );

  /* PACKAGE EVENTS */
  $router
    ->addRoute(
      PackageCreatedEvent::class,
      PackageCreatedListener::class
    )
    ->addRoute(
      PackageUpdatedEvent::class,
      PackageUpdatedListener::class
    );

  /* VERSION EVENTS */
  $router
    ->addRoute(
      VersionCreatedEvent::class,
      VersionCreatedListener::class
    )
    ->addRoute(
      VersionUpdatedEvent::class,
      VersionUpdatedListener::class
    );

  /* PACKAGE COMMANDS */
  $router->addRoute(
    PackageDiscoveryCommand::class,
    PackageDiscoveryHandler::class
  );

  /* DEPENDENCY COMMANDS */
  $router->addRoute(
    UpdateDependencyStatusCommand::class,
    UpdateDependencyStatusHandler::class
  );

  /* VERSION COMMANDS */
  $router->addRoute(
    UpdateVersionStatusCommand::class,
    UpdateVersionStatusHandler::class
  );
};
