<?php
declare(strict_types = 1);

namespace App\Application\Console\Package;

use App\Domain\Package\Package;
use App\Domain\Package\PackageRepositoryInterface;
use Buzz\Browser;
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

  protected static $defaultName = 'package:get-updates';
  private PackageRepositoryInterface $packageRepository;
  private Browser $browser;

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

      $dataPath = sys_get_temp_dir() . '/changes.json';

      $modTime = false;
      if (file_exists($dataPath)) {
        $modTime = filemtime($dataPath);
      }

      if ($modTime === false || (time() - $modTime) > self::FILE_TIMEOUT) {
        $url = "${mirror}/metadata/changes.json?since=${since}";
        if ($output->isVerbose()) {
          $io->text(
            sprintf(
              "[%s] Downloading a fresh copy of <options=bold;fg=cyan>${url}</>",
              date('H:i:s'),
            )
          );
        }

        $response = $this->browser->get($url, ['User-Agent' => 'php.package.health (twitter.com/flavioheleno)']);
        if ($response->getStatusCode() >= 400) {
          throw new RuntimeException(
            sprintf(
              'Request to "%s" returned status code %d',
              $url,
              $response->getStatusCode()
            )
          );
        }

        file_put_contents($dataPath, (string)$response->getBody());
      }

      $json = json_decode(file_get_contents($dataPath), true, 512, JSON_THROW_ON_ERROR);


      foreach ($json['actions'] as $action) {
        switch ($action['type']) {
          case 'update':
            break;
          case 'delete':
            break;
          case 'resync':
            break;
          default:
            //
        }

        $package = new Package($packageName, '', '', '');

        $this->packageRepository->save($package);
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
    Browser $browser
  ) {
    $this->packageRepository = $packageRepository;
    $this->browser           = $browser;

    parent::__construct();
  }
}
