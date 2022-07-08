<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Console\Queue;

use Courier\Client\Producer\ProducerInterface;
use Exception;
use InvalidArgumentException;
use PackageHealth\PHP\Application\Message\Event\Dependency\DependencyCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Dependency\DependencyUpdatedEvent;
use PackageHealth\PHP\Application\Message\Event\Package\PackageCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Package\PackageUpdatedEvent;
use PackageHealth\PHP\Application\Message\Event\Version\VersionCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Version\VersionUpdatedEvent;
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

final class SendEventCommand extends Command {
  protected static $defaultName = 'queue:send-event';
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
      ->setDescription('Send an event to the message bus')
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
        'eventClass',
        InputArgument::REQUIRED,
        'A fully-qualified event class name'
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

      $eventClass = $input->getArgument('eventClass');

      switch ($eventClass) {
        case DependencyCreatedEvent::class:
        case DependencyUpdatedEvent::class:
          if ($dependencyId === null) {
            throw new InvalidArgumentException('The option "--dependencyId" is required for this command');
          }

          $dependency = $this->dependencyRepository->get((int)$dependencyId);
          $this->producer->sendEvent(
            new $eventClass($dependency)
          );

          break;
        case PackageCreatedEvent::class:
        case PackageUpdatedEvent::class:
          if ($packageName === null) {
            throw new InvalidArgumentException('The option "--packageName" is required for this command');
          }

          $package = $this->packageRepository->get($packageName);
          $this->producer->sendEvent(
            new $eventClass($package)
          );

          break;
        case VersionCreatedEvent::class:
        case VersionUpdatedEvent::class:
          $versionId = $input->getOption('versionId');
          if ($versionId === null) {
            throw new InvalidArgumentException('The option "--versionId" is required for this event');
          }

          $version = $this->versionRepository->get((int)$versionId);
          $this->producer->sendEvent(
            new $eventClass($version)
          );
          break;
        default:
          throw new InvalidArgumentException(
            "Invalid event class '{$eventClass}'"
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
