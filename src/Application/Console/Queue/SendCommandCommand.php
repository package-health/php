<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Console\Queue;

use Courier\Client\Producer\ProducerInterface;
use Exception;
use InvalidArgumentException;
use PackageHealth\PHP\Application\Message\Command\PackageDiscoveryCommand;
use PackageHealth\PHP\Application\Message\Command\UpdateDependencyStatusCommand;
use PackageHealth\PHP\Application\Message\Command\UpdateVersionStatusCommand;
use PackageHealth\PHP\Domain\Dependency\DependencyRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SendCommandCommand extends Command {
  protected static $defaultName = 'queue:send-command';
  private ProducerInterface $producer;
  private DependencyRepositoryInterface $dependencyRepository;
  private PackageRepositoryInterface $packageRepository;
  private VersionRepositoryInterface $versionRepository;

  /**
   * Command configuration.
   *
   * @return void
   */
  protected function configure(): void {
    $this
      ->setDescription('Send a command to the message bus')
      ->addOption(
        'packageName',
        null,
        InputOption::VALUE_REQUIRED,
        ''
      )
      ->addOption(
        'versionId',
        null,
        InputOption::VALUE_REQUIRED,
        ''
      )
      ->addOption(
        'dependencyId',
        null,
        InputOption::VALUE_REQUIRED,
        ''
      )
      ->addArgument(
        'commandName',
        InputArgument::REQUIRED,
        'Command name'
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

      $commandName = (string)$input->getArgument('commandName');
      if (preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $commandName) !== 1) {
        throw new InvalidArgumentException(
          sprintf(
            'Invalid command name "%s"',
            $commandName
          )
        );
      }

      $commandClass = match ($commandName) {
        'PackageDiscovery' => PackageDiscoveryCommand::class,
        'UpdateDependencyStatus' => UpdateDependencyStatusCommand::class,
        'UpdateVersionStatus' => UpdateVersionStatusCommand::class,
        default => throw new InvalidArgumentException(
          sprintf(
            'Command "%s" not found',
            $commandName
          )
        )
      };

      $io->text(
        sprintf(
          '[%s] Command class: <options=bold;fg=cyan>%s</>',
          date('H:i:s'),
          $commandClass
        )
      );

      switch ($commandClass) {
        case PackageDiscoveryCommand::class:
          $packageName = $input->getOption('packageName');
          if ($packageName === null) {
            throw new InvalidArgumentException('The option "--packageName" is required for this command');
          }

          $this->producer->sendCommand(
            new $commandClass($packageName)
          );
          break;

        case UpdateDependencyStatusCommand::class:
          $packageName = $input->getOption('packageName');
          if ($packageName === null) {
            throw new InvalidArgumentException('The option "--packageName" is required for this command');
          }

          $packageCol = $this->packageRepository->findMatching(
            [
              'name' => $packageName
            ]
          );

          $io->text(
            sprintf(
              '[%s] Found <options=bold;fg=cyan>%d</> packages matching the name "<options=bold;fg=cyan>%s</>"',
              date('H:i:s'),
              count($packageCol),
              $packageName
            )
          );

          foreach ($packageCol as $package) {
            $io->text(
              sprintf(
                '[%s] Sending command for: <options=bold;fg=cyan>%s</>',
                date('H:i:s'),
                $package->getName()
              )
            );
            $this->producer->sendCommand(
              new $commandClass($package)
            );
          }

          break;

        case UpdateVersionStatusCommand::class:
          $dependencyId = $input->getOption('dependencyId');
          if ($dependencyId === null) {
            throw new InvalidArgumentException('The option "--dependencyId" is required for this command');
          }

          $dependency = $this->dependencyRepository->get((int)$dependencyId);
          $this->producer->sendCommand(
            new $commandClass($dependency)
          );
          break;

        default:
          throw new InvalidArgumentException(
            "Invalid command class '{$commandClass}'"
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

  public function __construct(
    ProducerInterface $producer,
    DependencyRepositoryInterface $dependencyRepository,
    PackageRepositoryInterface $packageRepository,
    VersionRepositoryInterface $versionRepository
  ) {
    $this->producer             = $producer;
    $this->dependencyRepository = $dependencyRepository;
    $this->packageRepository    = $packageRepository;
    $this->versionRepository    = $versionRepository;

    parent::__construct();
  }
}
