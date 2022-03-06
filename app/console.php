<?php
declare(strict_types = 1);

use App\Application\Console\Package\GetDataCommand;
use App\Application\Console\Package\GetListCommand;
use App\Application\Console\Package\GetUpdatesCommand;
use App\Application\Console\Package\MassImportCommand;
use DI\ContainerBuilder;
use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions([
    GetDataCommand::class    => autowire(GetDataCommand::class),
    GetListCommand::class    => autowire(GetListCommand::class),
    GetUpdatesCommand::class => autowire(GetUpdatesCommand::class),
    MassImportCommand::class  => autowire(MassImportCommand::class)
  ]);
};
