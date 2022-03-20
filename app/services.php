<?php
declare(strict_types = 1);

use App\Application\Service\Packagist;
use App\Application\Service\Storage\FileStorageInterface;
use App\Infrastructure\Storage\LocalFileStorage;
use DI\ContainerBuilder;
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
