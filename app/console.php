<?php
declare(strict_types = 1);

use App\Application\Console\Packagist\GetDataCommand;
use App\Application\Console\Packagist\GetListCommand;
use App\Application\Console\Packagist\GetUpdatesCommand;
use App\Application\Console\Packagist\MassImportCommand;
use App\Application\Console\Queue\QueueConsumeCommand;
use App\Application\Console\Queue\QueueListRoutesCommand;
use DI\ContainerBuilder;
use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
  $containerBuilder->addDefinitions(
    [
      GetDataCommand::class         => autowire(GetDataCommand::class),
      GetListCommand::class         => autowire(GetListCommand::class),
      GetUpdatesCommand::class      => autowire(GetUpdatesCommand::class),
      MassImportCommand::class      => autowire(MassImportCommand::class),
      QueueConsumeCommand::class    => autowire(QueueConsumeCommand::class),
      QueueListRoutesCommand::class => autowire(QueueListRoutesCommand::class)
    ]
  );
};
