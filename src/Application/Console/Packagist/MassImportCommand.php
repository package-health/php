<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Console\Packagist;

use Exception;
use InvalidArgumentException;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('packagist:mass-import', 'Get the metadata of a list of packages from a Packagist mirror')]
final class MassImportCommand extends Command {
  private PackageRepositoryInterface $packageRepository;

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
        'https://repo.packagist.org'
      )
      ->addArgument(
        'pattern',
        InputArgument::REQUIRED,
        'The package name pattern (e.g. symfony/*)'
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

      $pattern = $input->getArgument('pattern');

      $command = $this->getApplication()->find('packagist:get-data');

      $packages = $this->packageRepository->findMatching(
        [
          'name' => $pattern
        ]
      );

      if (count($packages) === 0) {
        $io->text(
          sprintf(
            '[%s] Could not find any packages that matches the pattern "%s"',
            date('H:i:s'),
            $pattern
          )
        );

        return Command::SUCCESS;
      }

      if ($output->isVerbose()) {
        $io->text(
          sprintf(
            '[%s] Found <options=bold;fg=cyan>%d</> packages',
            date('H:i:s'),
            count($packages)
          )
        );
      }

      foreach ($packages as $package) {
        if ($output->isVerbose()) {
          $io->text(
            sprintf(
              '[%s] Processing package <options=bold;fg=cyan>%s</>',
              date('H:i:s'),
              $package->getName()
            )
          );
        }

        $returnCode = $command->run(
          new ArrayInput(
            [
              '--mirror' => $mirror,
              'package'  => $package->getName()
            ]
          ),
          $output
        );
      }

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

  public function __construct(PackageRepositoryInterface $packageRepository) {
    $this->packageRepository = $packageRepository;

    parent::__construct();
  }
}
