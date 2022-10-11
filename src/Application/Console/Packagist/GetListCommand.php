<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Console\Packagist;

use Courier\Client\Producer;
use Exception;
use InvalidArgumentException;
use PackageHealth\PHP\Application\Message\Command\PackageDiscoveryCommand;
use PackageHealth\PHP\Application\Message\Command\PackagePurgeCommand;
use PackageHealth\PHP\Application\Service\Packagist;
use PackageHealth\PHP\Domain\Package\Package;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('packagist:get-list', 'Get the complete list of packages from a Packagist mirror')]
final class GetListCommand extends Command implements SignalableCommandInterface {
  private PackageRepositoryInterface $packageRepository;
  private Producer $producer;
  private Packagist $packagist;
  private bool $stopDaemon = false;

  /**
   * Command configuration.
   *
   * @return void
   */
  protected function configure(): void {
    $this
      ->addOption(
        'resync',
        'r',
        InputOption::VALUE_NONE,
        'Resync the list'
      )
      ->addOption(
        'skipAdd',
        null,
        InputOption::VALUE_NONE,
        'Skip adding new packages'
      )
      ->addOption(
        'skipRem',
        null,
        InputOption::VALUE_NONE,
        'Skip removing packages'
      )
      ->addOption(
        'offline',
        null,
        InputOption::VALUE_NONE,
        'Work in offline mode'
      )
      ->addOption(
        'mirror',
        'm',
        InputOption::VALUE_REQUIRED,
        'Packagist mirror url',
        'https://packagist.org'
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

      $resync = (bool)$input->getOption('resync');

      if ($resync) {
        $io->text(
          sprintf(
            '[%s] Running in resync mode',
            date('H:i:s')
          )
        );
      }

      $workOffline = (bool)$input->getOption('offline');

      if ($workOffline) {
        $io->text(
          sprintf(
            '[%s] Running in <options=bold;fg=red>offline</> mode',
            date('H:i:s')
          )
        );

        $this->packagist->workOffline();
      }

      $mirror = $input->getOption('mirror');
      if (filter_var($mirror, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('Invalid mirror option');
      }

      $packageList = $this->packagist->getPackageList($mirror);

      $listCount = count($packageList);

      $io->text(
        sprintf(
          '[%s] Got <options=bold;fg=cyan>%s</> package(s) from Packagist',
          date('H:i:s'),
          number_format(
            $listCount,
            0,
            ',',
            '.'
          )
        )
      );

      $packages = [];
      if ($resync === false) {
        $packageCol = $this->packageRepository->all();
        $packages = $packageCol->map(
          static function (Package $package): string {
            return $package->getName();
          }
        )->toArray();

        $storedCount = count($packages);
        $io->text(
          sprintf(
            '[%s] Local storage has <options=bold;fg=cyan>%s</> package(s)',
            date('H:i:s'),
            number_format(
              $storedCount,
              0,
              ',',
              '.'
            )
          )
        );
      }

      $addList = array_diff($packageList, $packages);
      $io->text(
        sprintf(
          '[%s] <options=bold;fg=green>%s</> package(s) will be added',
          date('H:i:s'),
          number_format(
            count($addList),
            0,
            ',',
            '.'
          )
        )
      );

      $remList = array_diff($packages, $packageList);
      $io->text(
        sprintf(
          '[%s] <options=bold;fg=red>%s</> package(s) will be removed',
          date('H:i:s'),
          number_format(
            count($remList),
            0,
            ',',
            '.'
          )
        )
      );

      $skipAdd = (bool)$input->getOption('skipAdd');
      if ($skipAdd === false) {
        foreach ($addList as $packageName) {
          $this->producer->sendCommand(
            new PackageDiscoveryCommand($packageName, workOffline: $workOffline)
          );

          if ($this->stopDaemon === true) {
            $io->text(
              sprintf(
                '[%s] Interrupted, leaving',
                date('H:i:s')
              )
            );

            return Command::SUCCESS;
          }
        }
      }

      $skipRem = (bool)$input->getOption('skipRem');
      if ($skipRem === false) {
        foreach ($remList as $packageName) {
          $this->producer->sendCommand(
            new PackagePurgeCommand($packageName)
          );

          if ($this->stopDaemon === true) {
            $io->text(
              sprintf(
                '[%s] Interrupted, leaving',
                date('H:i:s')
              )
            );

            return Command::SUCCESS;
          }
        }
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

  public function __construct(
    PackageRepositoryInterface $packageRepository,
    Producer $producer,
    Packagist $packagist
  ) {
    $this->packageRepository = $packageRepository;
    $this->producer          = $producer;
    $this->packagist         = $packagist;

    parent::__construct();
  }

  public function getSubscribedSignals(): array {
    return [SIGINT, SIGTERM];
  }

  public function handleSignal(int $signal): void {
    $this->stopDaemon = true;
  }
}
