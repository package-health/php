<?php
declare(strict_types = 1);

namespace App\Application\Console\Packagist;

use App\Application\Message\Command\PackageDiscoveryCommand;
use App\Application\Service\Packagist;
use App\Domain\Package\PackageRepositoryInterface;
use App\Domain\Preference\PreferenceRepositoryInterface;
use App\Domain\Preference\PreferenceTypeEnum;
use Courier\Client\Producer;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GetUpdatesCommand extends Command {
  /**
   * File cache lifetime (12 hour TTL)
   */
  private const FILE_TIMEOUT = 43200;

  protected static $defaultName = 'packagist:get-updates';
  private PreferenceRepositoryInterface $preferenceRepository;
  private PackageRepositoryInterface $packageRepository;
  private Packagist $packagist;
  private Producer $producer;

  /**
   * Command configuration.
   *
   * @return void
   */
  protected function configure(): void {
    $this
      ->setDescription('Get the list of package updates from a Packagist mirror')
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

      $preferenceCol = $this->preferenceRepository->find(
        [
          'category' => 'packagist',
          'property' => 'timestamp'
        ]
      );

      $preference = $preferenceCol[0] ?? null;
      if ($preference === null) {
        $since = $this->packagist->getChangesTimestamp();
        $preference = $this->preferenceRepository->create(
          'packagist',
          'timestamp',
          (string)$since,
          PreferenceTypeEnum::isInteger
        );
      }

      $changes = $this->packagist->getPackageUpdates($preference->getValueAsInteger());
      foreach ($changes['changes'] as $action) {
        switch ($action['type']) {
          case 'update':
            $packageName = $action['package'];
            if (str_ends_with($packageName, '~dev')) {
              $packageName = substr($packageName, 0, strlen($packageName) - 4);
            }

            $packageCol = $this->packageRepository->find(['name' => $packageName]);

            $package = $packageCol[0] ?? null;
            if ($package === null) {
              $package = $this->packageRepository->create($packageName);
            }

            $this->producer->sendCommand(
              new PackageDiscoveryCommand($package)
            );

            break;
          case 'delete':
            // TODO
            // $this->producer->sendCommand(
            //   new PackageRemoveCommand($package);
            // );

            break;
          case 'resync':
            break;
          default:
            //
        }
      }

      $preference = $preference->withIntegerValue($changes['timestamp']);
      if ($preference->isDirty()) {
        $this->preferenceRepository->update($preference);
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
    PreferenceRepositoryInterface $preferenceRepository,
    PackageRepositoryInterface $packageRepository,
    Packagist $packagist,
    Producer $producer
  ) {
    $this->preferenceRepository = $preferenceRepository;
    $this->packageRepository    = $packageRepository;
    $this->packagist            = $packagist;
    $this->producer             = $producer;

    parent::__construct();
  }
}
