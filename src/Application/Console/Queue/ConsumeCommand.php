<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Console\Queue;

use Courier\Client\Consumer;
use Exception;
use InvalidArgumentException;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('queue:consume', 'Consume messages from a bus queue')]
final class ConsumeCommand extends Command implements SignalableCommandInterface {
  private Consumer $consumer;
  private bool $stopDaemon = false;

  /**
   * Command configuration.
   *
   * @return void
   */
  protected function configure(): void {
    $this
      ->addOption(
        'daemonize',
        'd',
        InputOption::VALUE_NONE,
        'Run command as daemon'
      )
      ->addOption(
        'all',
        null,
        InputOption::VALUE_NONE,
        'Consume all available messages (overrides messageCount parameter)'
      )
      ->addOption(
        'messageCount',
        'm',
        InputOption::VALUE_REQUIRED,
        'Number of messages to consume',
        1
      )
      ->addArgument(
        'queueName',
        InputArgument::REQUIRED,
        'Name of the bus queue'
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

      $daemonize = (bool)$input->getOption('daemonize');

      $queueName = $input->getArgument('queueName');

      $messageCount = match ((bool)$input->getOption('all')) {
        true  => $this->consumer->getMessageCount($queueName),
        false => (int)$input->getOption('messageCount')
      };

      if ($output->isVerbose()) {
        $io->text(
          sprintf(
            '[%s] Queue name: <options=bold;fg=green>%s</>',
            date('H:i:s'),
            $queueName
          )
        );
        $io->text(
          sprintf(
            '[%s] Message count: <options=bold;fg=green>%d</>',
            date('H:i:s'),
            $messageCount
          )
        );
      }

      $timer = new Timer();
      do {
        $timer->start();

        $consumed = $this->consumer->consume($queueName, $messageCount);

        $duration = $timer->stop();
        if ($output->isVerbose()) {
          $io->text(
            sprintf(
              '[%s] Consumed messages: <options=bold;fg=green>%d</>',
              date('H:i:s'),
              $consumed
            )
          );
          $io->text(
            sprintf(
              '[%s] Elapsed time: <options=bold;fg=green>%.2f</>ms',
              date('H:i:s'),
              $duration->asMilliseconds()
            )
          );

          if ($consumed > 1) {
            $io->text(
              sprintf(
                '[%s] Rate: <options=bold;fg=green>%.2f</> messages per second',
                date('H:i:s'),
                $consumed / $duration->asSeconds()
              )
            );
          }
        }

        if ($this->stopDaemon === true) {
          $io->text(
            sprintf(
              '[%s] Interrupted, leaving',
              date('H:i:s')
            )
          );

          return Command::SUCCESS;
        }
      } while ($daemonize && $this->stopDaemon === false);

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

  public function __construct(Consumer $consumer) {
    $this->consumer = $consumer;

    parent::__construct();
  }

  public function getSubscribedSignals(): array {
    return [SIGINT, SIGTERM];
  }

  public function handleSignal(int $signal): void {
    $this->stopDaemon = true;
    echo 'Waiting to stop daemon..', PHP_EOL;
  }
}
