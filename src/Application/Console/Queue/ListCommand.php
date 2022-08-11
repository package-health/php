<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Console\Queue;

use Courier\Bus;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ListCommand extends Command {
  protected static $defaultName = 'queue:list';
  private Bus $bus;

  /**
   * Command configuration.
   *
   * @return void
   */
  protected function configure(): void {
    $this
      ->setDescription('List message bus available queues (bound routes)');
  }

  /**
   * Command execution.
   *
   * @param \Symfony\Component\Console\Input\InputInterface   $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    try {
      // i/o styling
      $io = new SymfonyStyle($input, $output);

      $table = new Table($output);
      $table->setHeaders(
        [
          'Name',
          'Queue',
          'Pending'
        ]
      );

      $router = $this->bus->getRouter();
      $transp = $this->bus->getTransport();
      foreach ($router->getRoutes() as $route) {
        $table->addRow(
          [
            $route->getRouteName(),
            $route->getQueueName(),
            number_format(
              $transp->pending($route->getQueueName()),
              0,
              ',',
              '.'
            )
          ]
        );
      }

      $table->render();
    } catch (Exception $exception) {
      $io->error(
        sprintf(
          '[%s] %s',
          date('H:i:s'),
          $exception->getMessage()
        )
      );
      if ($output->isDebug()) {
        $io->listing(explode(PHP_EOL, $exception->getTraceAsString()));
      }

      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  public function __construct(Bus $bus) {
    $this->bus = $bus;

    parent::__construct();
  }
}
