<?php
declare(strict_types = 1);

use App\Application\Console\Package\GetDataCommand;
use App\Application\Console\Package\GetListCommand;
use App\Application\Console\Package\GetUpdatesCommand;
use App\Application\Console\Package\MassImportCommand;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions([
    GetDataCommand::class    => \DI\autowire(GetDataCommand::class),
    GetListCommand::class    => \DI\autowire(GetListCommand::class),
    GetUpdatesCommand::class => \DI\autowire(GetUpdatesCommand::class),
    MassImportCommand::class  => \DI\autowire(MassImportCommand::class)
  ]);
};
