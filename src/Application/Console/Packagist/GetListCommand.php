<?php
declare(strict_types = 1);

namespace App\Application\Console\Packagist;

use App\Application\Message\Command\PackageDiscoveryCommand;
use App\Application\Message\Event\Package\PackageCreatedEvent;
use App\Application\Message\Event\Package\PackageRemovedEvent;
use App\Domain\Package\Package;
use App\Domain\Package\PackageRepositoryInterface;
use App\Application\Service\Packagist;
use Courier\Client\Producer;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GetListCommand extends Command {
  /**
   * File cache lifetime (12 hour TTL)
   */
  private const FILE_TIMEOUT = 43200;

  protected static $defaultName = 'packagist:get-list';
  private PackageRepositoryInterface $packageRepository;
  private Producer $producer;
  private Packagist $packagist;

  /**
   * Command configuration.
   *
   * @return void
   */
  protected function configure(): void {
    $this
      ->setDescription('Get the complete list of packages from a Packagist mirror')
      ->addOption(
        'resync',
        'r',
        InputOption::VALUE_NONE,
        'Resync the list'
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
   * @return int|null
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

      $packageList = $this->packagist->getPackageList($mirror);

      $listCount = count($packageList);

      $io->text(
        sprintf(
          '[%s] Got <options=bold;fg=cyan>%d</> package(s) from Packagist',
          date('H:i:s'),
          $listCount
        )
      );

      if ((bool)$input->getOption('resync')) {
        $io->text(
          sprintf(
            '[%s] Running in resync mode',
            date('H:i:s')
          )
        );

        foreach ($packageList as $package) {
          if ($this->packageRepository->exists($package)) {
            $package = $this->packageRepository->get($package);
          } else {
            $package = $this->packageRepository->create($package);
          }

          $this->producer->sendCommand(
            new PackageDiscoveryCommand($package)
          );
        }

        $io->text(
          sprintf(
            '[%s] Done',
            date('H:i:s')
          )
        );

        return Command::SUCCESS;
      }

      $packageCol = $this->packageRepository->all();
      $packages = $packageCol->map(
        static function (Package $package): string {
          return $package->getName();
        }
      )->toArray();

      $storedCount = count($packages);
      $io->text(
        sprintf(
          '[%s] Local storage has <options=bold;fg=cyan>%d</> package(s)',
          date('H:i:s'),
          $storedCount
        )
      );

      $addList = array_diff($packageList, $packages);
      $io->text(
        sprintf(
          '[%s] <options=bold;fg=green>%d</> package(s) will be added',
          date('H:i:s'),
          count($addList)
        )
      );

      $removeList = array_diff($packages, $packageList);
      $io->text(
        sprintf(
          '[%s] <options=bold;fg=red>%d</> package(s) will be removed',
          date('H:i:s'),
          count($removeList)
        )
      );

      foreach ($addList as $packageName) {
        $package = $this->packageRepository->create($packageName);

        $this->producer->sendEvent(
          new PackageCreatedEvent($package)
        );
      }

      foreach ($removeList as $packageName) {
        // $this->packageRepository->delete($package);
        // $this->producer->sendEvent(
        //   new PackageRemovedEvent($package)
        // );
      }

      $io->text(
        sprintf(
          '[%s] Done',
          date('H:i:s')
        )
      );
    } catch (Exception $exception) {
      if (isset($io) === true) {
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
}
