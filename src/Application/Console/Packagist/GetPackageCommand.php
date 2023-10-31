<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Console\Packagist;

use Courier\Client\Producer;
use Exception;
use InvalidArgumentException;
use PackageHealth\PHP\Application\Message\Command\PackageDiscoveryCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('packagist:get-package', 'Get the metadata of a package from a Packagist mirror')]
final class GetPackageCommand extends Command {
  /**
   * File cache lifetime (12 hour TTL)
   */
  private const FILE_TIMEOUT = 43200;

  private Producer $producer;

  /**
   * Command configuration.
   *
   * @return void
   */
  protected function configure(): void {
    $this
      ->addOption(
        'mirror',
        'm',
        InputOption::VALUE_REQUIRED,
        'Packagist mirror url',
        'https://packagist.org'
      )
      ->addOption(
        'offline',
        null,
        InputOption::VALUE_NONE,
        'Work in offline mode'
      )
      ->addArgument(
        'package',
        InputArgument::REQUIRED,
        'The package name (e.g. symfony/console)'
      );
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
      $io->text(
        sprintf(
          '[%s] Started with pid <options=bold;fg=cyan>%d</>',
          date('H:i:s'),
          posix_getpid()
        )
      );

      $mirror = $input->getOption('mirror');
      if (filter_var($mirror, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('Invalid mirror option');
      }

      $workOffline = (bool)$input->getOption('offline');
      $packageName = $input->getArgument('package');

      $this->producer->sendCommand(
        new PackageDiscoveryCommand($packageName, workOffline: $workOffline)
      );

      $io->text(
        sprintf(
          '[%s] Done',
          date('H:i:s')
        )
      );
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

  public function __construct(Producer $producer) {
    $this->producer = $producer;

    parent::__construct();
  }
}
