<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Console\Packagist;

use Courier\Client\Producer\ProducerInterface;
use Exception;
use InvalidArgumentException;
use PackageHealth\PHP\Application\Message\Command\PackageDiscoveryCommand;
use PackageHealth\PHP\Application\Service\Packagist;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Preference\PreferenceRepositoryInterface;
use PackageHealth\PHP\Domain\Preference\PreferenceTypeEnum;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('packagist:get-updates', 'Get the list of package updates from a Packagist mirror')]
final class GetUpdatesCommand extends Command {
  private PreferenceRepositoryInterface $preferenceRepository;
  private PackageRepositoryInterface $packageRepository;
  private Packagist $packagist;
  private ProducerInterface $producer;

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

      $preferenceCol = $this->preferenceRepository->find(
        [
          'category' => 'packagist',
          'property' => 'timestamp'
        ],
        1
      );

      $preference = $preferenceCol->first();
      if ($preference === null) {
        $io->text(
          sprintf(
            '[%s] Last update not set',
            date('H:i:s')
          )
        );

        // https://packagist.org/apidoc#track-package-updates
        $updates = $this->packagist->getPackageUpdates(time() * 10000);
        $preference = $this->preferenceRepository->create(
          'packagist',
          'timestamp',
          (string)$updates['timestamp'],
          PreferenceTypeEnum::isInteger
        );
      }

      $io->text(
        sprintf(
          '[%s] Checkpoint: "<options=bold;fg=cyan>%d</>" (<options=bold;fg=cyan>%s</>)',
          date('H:i:s'),
          $preference->getValueAsInteger(),
          date('H:i:s d/m/Y', (int)($preference->getValueAsInteger() / 10000))
        )
      );

      $updates = $this->packagist->getPackageUpdates($preference->getValueAsInteger());

      $packageList = [];
      foreach ($updates['actions'] as $action) {
        switch ($action['type']) {
          case 'update':
            $packageName = $action['package'];
            if (str_ends_with($packageName, '~dev')) {
              $packageName = substr($packageName, 0, strlen($packageName) - 4);
            }

            if (in_array($packageName, $packageList) === true) {
              continue 2;
            }

            $packageList[] = $packageName;

            $io->text(
              sprintf(
                '[%s] Update package: "<options=bold;fg=cyan>%s</>" (%s)',
                date('H:i:s'),
                $packageName,
                date('H:i:s d/m/Y', $action['time'])
              )
            );

            $this->producer->sendCommand(
              new PackageDiscoveryCommand($packageName)
            );

            break;
          case 'delete':
            // $io->text(
            //   sprintf(
            //     '[%s] Remove package: "<options=bold;fg=cyan>%s</>"',
            //     date('H:i:s'),
            //     $action['package']
            //   )
            // );
            // TODO
            // $this->producer->sendCommand(
            //   new PackageRemoveCommand($package);
            // );

            break;
          case 'resync':
            $io->text(
              sprintf(
                '[%s] Resync local database',
                date('H:i:s')
              )
            );

            $command = $this->getApplication()->find('packagist:get-list');
            $returnCode = $command->run(
              new ArrayInput(
                [
                  '--resync' => true,
                  '--mirror' => $mirror
                ]
              ),
              $output
            );

            break;
          default:
            $io->text(
              sprintf(
                '[%s] Unknown action type: "<options=bold;fg=cyan>%s</>"',
                date('H:i:s'),
                $action['type']
              )
            );
        }
      }

      $preference = $preference->withIntegerValue($updates['timestamp']);
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
    PreferenceRepositoryInterface $preferenceRepository,
    PackageRepositoryInterface $packageRepository,
    Packagist $packagist,
    ProducerInterface $producer
  ) {
    $this->preferenceRepository = $preferenceRepository;
    $this->packageRepository    = $packageRepository;
    $this->packagist            = $packagist;
    $this->producer             = $producer;

    parent::__construct();
  }
}
