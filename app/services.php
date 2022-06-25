<?php
declare(strict_types = 1);

use DI\ContainerBuilder;
use PackageHealth\PHP\Application\Service\Packagist;
use PackageHealth\PHP\Application\Service\Storage\FileStorageInterface;
use PackageHealth\PHP\Infrastructure\Storage\LocalFileStorage;
use Psr\Container\ContainerInterface;
use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions(
    [
      Packagist::class => autowire(Packagist::class),
      FileStorageInterface::class => static function (ContainerInterface $container): FileStorageInterface {
        return new LocalFileStorage(__DIR__ . '/../run');
      },
    ]
  );
};
