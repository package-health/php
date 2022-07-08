<?php
declare(strict_types = 1);

use DI\ContainerBuilder;
use PackageHealth\PHP\Application\Console\Packagist\GetDataCommand;
use PackageHealth\PHP\Application\Console\Packagist\GetListCommand;
use PackageHealth\PHP\Application\Console\Packagist\GetUpdatesCommand;
use PackageHealth\PHP\Application\Console\Packagist\MassImportCommand;
use PackageHealth\PHP\Application\Console\Queue\ConsumeCommand;
use PackageHealth\PHP\Application\Console\Queue\ListCommand;
use PackageHealth\PHP\Application\Console\Queue\SendCommandCommand;
use PackageHealth\PHP\Application\Console\Queue\SendEventCommand;
use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions(
    [
      GetDataCommand::class     => autowire(GetDataCommand::class),
      GetListCommand::class     => autowire(GetListCommand::class),
      GetUpdatesCommand::class  => autowire(GetUpdatesCommand::class),
      MassImportCommand::class  => autowire(MassImportCommand::class),
      ConsumeCommand::class     => autowire(ConsumeCommand::class),
      ListCommand::class        => autowire(ListCommand::class),
      SendCommandCommand::class => autowire(SendCommandCommand::class),
      SendEventCommand::class   => autowire(SendEventCommand::class)
    ]
  );
};
