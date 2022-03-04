<?php
declare(strict_types = 1);

use App\Application\Listeners\DependencyListener;
use App\Application\Listeners\PackageListener;
use App\Application\Listeners\StatsListener;
use App\Application\Listeners\VersionListener;
use App\Domain\Dependency\Dependency;
use App\Domain\Package\Package;
use App\Domain\Stats\Stats;
use App\Domain\Version\Version;
use Evenement\EventEmitter;
use Psr\Container\ContainerInterface;

return function (ContainerInterface $container): void {
  $eventEmitter = $container->get(EventEmitter::class);

  /* DEPENDENCY EVENTS */
  $eventEmitter->on(
    'dependency.created',
    function (Dependency $dependency) use ($container): void {
      $listener = $container->get(DependencyListener::class);
      $listener->onCreated($dependency);
    }
  );
  $eventEmitter->on(
    'dependency.updated',
    function (Dependency $dependency) use ($container): void {
      $listener = $container->get(DependencyListener::class);
      $listener->onUpdated($dependency);
    }
  );
  $eventEmitter->on(
    'dependency.deleted',
    function (Dependency $dependency) use ($container): void {
      $listener = $container->get(DependencyListener::class);
      $listener->onDeleted($dependency);
    }
  );

  /* PACKAGE EVENTS */
  $eventEmitter->on(
    'package.created',
    function (Package $package) use ($container): void {
      $listener = $container->get(PackageListener::class);
      $listener->onCreated($package);
    }
  );
  $eventEmitter->on(
    'package.updated',
    function (Package $package) use ($container): void {
      $listener = $container->get(PackageListener::class);
      $listener->onUpdated($package);
    }
  );
  $eventEmitter->on(
    'package.deleted',
    function (Package $package) use ($container): void {
      $listener = $container->get(PackageListener::class);
      $listener->onDeleted($package);
    }
  );

  /* STATS EVENTS */
  $eventEmitter->on(
    'stats.created',
    function (Stats $stats) use ($container): void {
      $listener = $container->get(StatsListener::class);
      $listener->onCreated($stats);
    }
  );
  $eventEmitter->on(
    'stats.updated',
    function (Stats $stats) use ($container): void {
      $listener = $container->get(StatsListener::class);
      $listener->onUpdated($stats);
    }
  );
  $eventEmitter->on(
    'stats.deleted',
    function (Stats $stats) use ($container): void {
      $listener = $container->get(StatsListener::class);
      $listener->onDeleted($stats);
    }
  );

  /* VERSION EVENTS */
  $eventEmitter->on(
    'version.created',
    function (Version $version) use ($container): void {
      $listener = $container->get(VersionListener::class);
      $listener->onCreated($version);
    }
  );
  $eventEmitter->on(
    'version.updated',
    function (Version $version) use ($container): void {
      $listener = $container->get(VersionListener::class);
      $listener->onUpdated($version);
    }
  );
  $eventEmitter->on(
    'version.deleted',
    function (Version $version) use ($container): void {
      $listener = $container->get(VersionListener::class);
      $listener->onDeleted($version);
    }
  );
};
