<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Console\Packagist;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Courier\Client\Producer\ProducerInterface;
use Exception;
use InvalidArgumentException;
use PackageHealth\PHP\Application\Message\Event\Dependency\DependencyCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Dependency\DependencyUpdatedEvent;
use PackageHealth\PHP\Application\Message\Event\Package\PackageUpdatedEvent;
use PackageHealth\PHP\Application\Message\Event\Stats\StatsCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Stats\StatsUpdatedEvent;
use PackageHealth\PHP\Application\Message\Event\Version\VersionCreatedEvent;
use PackageHealth\PHP\Application\Message\Event\Version\VersionUpdatedEvent;
use PackageHealth\PHP\Application\Service\Packagist;
use PackageHealth\PHP\Domain\Dependency\DependencyRepositoryInterface;
use PackageHealth\PHP\Domain\Dependency\DependencyStatusEnum;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Stats\StatsRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionStatusEnum;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('packagist:get-data', 'Get the metadata of a package from a Packagist mirror')]
final class GetDataCommand extends Command {
  /**
   * File cache lifetime (12 hour TTL)
   */
  private const FILE_TIMEOUT = 43200;

  private PackageRepositoryInterface $packageRepository;
  private VersionRepositoryInterface $versionRepository;
  private DependencyRepositoryInterface $dependencyRepository;
  private StatsRepositoryInterface $statsRepository;
  private ProducerInterface $producer;
  private VersionParser $versionParser;
  private Packagist $packagist;

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

      $packageName = $input->getArgument('package');

      $metadata = $this->packagist->getPackageMetadataVersion1($packageName, $mirror);

      $packageCol = $this->packageRepository->find(
        [
          'name' => "{$vendor}/{$project}"
        ],
        1
      );

      if ($packageCol->isEmpty()) {
        throw new PackageNotFoundException();
      }

      $package = $packageCol->first();
      $package = $package
        ->withDescription($metadata['description'] ?? '')
        ->withUrl($metadata['repository'] ?? '');
      $package = $this->packageRepository->update($package);

      // current latest version to check for updates
      $latestVersion           = $package->getLatestVersion();
      $latestVersionNormalized = $latestVersion ?? $this->versionParser->normalize($latestVersion);

      if ($this->statsRepository->exists($packageName)) {
        $stats = $this->statsRepository->get($packageName);
        $stats = $stats
          ->withGithubStars($metadata['github_stars'] ?? 0)
          ->withGithubWatchers($metadata['github_watchers'] ?? 0)
          ->withGithubForks($metadata['github_forks'] ?? 0)
          ->withDependents($metadata['dependents'] ?? 0)
          ->withSuggesters($metadata['suggesters'] ?? 0)
          ->withFavers($metadata['favers'] ?? 0)
          ->withTotalDownloads($metadata['downloads']['total'] ?? 0)
          ->withMonthlyDownloads($metadata['downloads']['monthly'] ?? 0)
          ->withDailyDownloads($metadata['downloads']['daily'] ?? 0);

        $stats = $this->statsRepository->update($stats);
      } else {
        $stats = $this->statsRepository->create(
          $packageName,
          $metadata['github_stars'] ?? 0,
          $metadata['github_watchers'] ?? 0,
          $metadata['github_forks'] ?? 0,
          $metadata['dependents'] ?? 0,
          $metadata['suggesters'] ?? 0,
          $metadata['favers'] ?? 0,
          $metadata['downloads']['total'] ?? 0,
          $metadata['downloads']['monthly'] ?? 0,
          $metadata['downloads']['daily'] ?? 0
        );
      }

      if ($output->isVerbose()) {
        $io->text(
          sprintf(
            '[%s] Found <options=bold;fg=cyan>%d</> releases',
            date('H:i:s'),
            count($metadata['versions'])
          )
        );
      }

      if (count($metadata['versions']) === 0) {
        return Command::SUCCESS;
      }

      foreach (array_reverse($metadata['versions']) as $release) {
        // exclude branches from tagged releases (https://getcomposer.org/doc/articles/versions.md#branches)
        $isBranch = preg_match('/^dev-|-dev$/', $release['version']) === 1;

        $versionCol = $this->versionRepository->find(
          [
            'package_id' => $package->getId(),
            'number'     => $release['version'],
            'normalized' => $release['version_normalized'],
            'release'    => $isBranch === false
          ],
          1
        );

        $version = $versionCol->first();
        if ($version === null) {
          if ($output->isVeryVerbose()) {
            $io->text(
              sprintf(
                '[%s] New %s <options=bold;fg=cyan>%s</> found',
                date('H:i:s'),
                $isBranch ? 'branch' : 'version',
                $release['version']
              )
            );
          }

          $version = $this->versionRepository->create(
            $package->getId(),
            $release['version'],
            $release['version_normalized'],
            $isBranch === false,
            VersionStatusEnum::Unknown
          );
        }

        // track new version releases
        if ($isBranch === false && Comparator::greaterThan($release['version_normalized'], $latestVersionNormalized)) {
          $latestVersion           = $release['version'];
          $latestVersionNormalized = $release['version_normalized'];
        }

        // track "require" dependencies
        $filteredRequire = array_filter(
          $release['require'] ?? [],
          static function (string $key): bool {
            return preg_match('/^(php|hhvm|ext-.*|lib-.*|pear-.*)$/', $key) !== 1 &&
              preg_match('/^[^\/]+\/[^\/]+$/', $key) === 1;
          },
          ARRAY_FILTER_USE_KEY
        );

        // flag packages without require dependencies with VersionStatusEnum::NoDeps
        if (empty($filteredRequire)) {
          if ($output->isDebug()) {
            $io->text(
              sprintf(
                '[%s] %s <options=bold;fg=cyan>%s</> has no required dependencies',
                date('H:i:s'),
                $isBranch ? 'Branch' : 'Version',
                $version->getNumber()
              )
            );
          }

          $version = $version->withStatus(VersionStatusEnum::NoDeps);
          $version = $this->versionRepository->update($version);
        }

        foreach ($filteredRequire as $dependencyName => $constraint) {
          if ($constraint === 'self.version') {
            // need to find out how to handle this
            continue;
          }

          if (isset($packageList[$dependencyName]) === false) {
            $packageCol = $this->packageRepository->find(
              [
                'name' => $dependencyName
              ],
              1
            );

            $packageList[$dependencyName] = $packageCol[0]->getLatestVersion() ?? '';
          }

          $dependencyCol = $this->dependencyRepository->find(
            [
              'version_id'  => $version->getId(),
              'name'        => $dependencyName,
              'development' => false
            ],
            1
          );

          if ($dependencyCol->isEmpty() === false) {
            $dependency = $dependencyCol->first();
            $dependency = $dependency
              ->withConstraint($constraint)
              ->withStatus(
                empty($packageList[$dependencyName]) ?
                DependencyStatusEnum::Unknown :
                (
                  Semver::satisfies($packageList[$dependencyName], $constraint) ?
                  DependencyStatusEnum::UpToDate :
                  DependencyStatusEnum::Outdated
                )
              );
            $dependency = $this->dependencyRepository->update($dependency);

            continue;
          }

          $dependency = $this->dependencyRepository->create(
            $version->getId(),
            $dependencyName,
            $constraint,
            false,
            empty($packageList[$dependencyName]) ?
            DependencyStatusEnum::Unknown :
            (
              Semver::satisfies($packageList[$dependencyName], $constraint) ?
              DependencyStatusEnum::UpToDate :
              DependencyStatusEnum::Outdated
            )
          );
        }

        // track "require-dev" dependencies
        $filteredRequireDev = array_filter(
          $release['require-dev'] ?? [],
          static function (string $key): bool {
            return preg_match('/^(php|hhvm|ext-.*|lib-.*|pear-.*)$/', $key) !== 1 &&
              preg_match('/^[^\/]+\/[^\/]+$/', $key) === 1;
          },
          ARRAY_FILTER_USE_KEY
        );

        if (empty($filteredRequireDev) && $output->isDebug()) {
          $io->text(
            sprintf(
              '[%s] %s <options=bold;fg=cyan>%s</> has no required development dependencies',
              date('H:i:s'),
              $isBranch ? 'Branch' : 'Version',
              $version->getNumber()
            )
          );
        }

        foreach ($filteredRequireDev as $dependencyName => $constraint) {
          if ($constraint === 'self.version') {
            // need to find out how to handle this
            continue;
          }

          if (isset($packageList[$dependencyName]) === false) {
            $packageCol = $this->packageRepository->find(
              [
                'name' => $dependencyName
              ],
              1
            );

            $packageList[$dependencyName] = $packageCol[0]->getLatestVersion() ?? '';
          }

          $dependencyCol = $this->dependencyRepository->find(
            [
              'version_id'  => $version->getId(),
              'name'        => $dependencyName,
              'development' => true
            ],
            1
          );

          if ($dependencyCol->isEmpty() === false) {
            $dependency = $dependencyCol->first();
            $dependency = $dependency
              ->withConstraint($constraint)
              ->withStatus(
                empty($packageList[$dependencyName]) ?
                DependencyStatusEnum::Unknown :
                (
                  Semver::satisfies($packageList[$dependencyName], $constraint) ?
                  DependencyStatusEnum::UpToDate :
                  DependencyStatusEnum::Outdated
                )
              );
            $dependency = $this->dependencyRepository->update($dependency);

            continue;
          }

          $dependency = $this->dependencyRepository->create(
            $version->getId(),
            $dependencyName,
            $constraint,
            true,
            empty($packageList[$dependencyName]) ?
            DependencyStatusEnum::Unknown :
            (
              Semver::satisfies($packageList[$dependencyName], $constraint) ?
              DependencyStatusEnum::UpToDate :
              DependencyStatusEnum::Outdated
            )
          );
        }
      }

      // update package latest tagged version if it was changed
      $package = $package->withLatestVersion($latestVersion);
      if ($package->isDirty()) {
        if ($output->isVerbose()) {
          $io->text(
            sprintf(
              '[%s] Updating latest package release to version <options=bold;fg=green>%s</>',
              date('H:i:s'),
              $latestVersion
            )
          );
        }

        $package = $this->packageRepository->update($package);
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
    VersionRepositoryInterface $versionRepository,
    DependencyRepositoryInterface $dependencyRepository,
    StatsRepositoryInterface $statsRepository,
    ProducerInterface $producer,
    VersionParser $versionParser,
    Packagist $packagist
  ) {
    $this->packageRepository    = $packageRepository;
    $this->versionRepository    = $versionRepository;
    $this->dependencyRepository = $dependencyRepository;
    $this->statsRepository      = $statsRepository;
    $this->producer             = $producer;
    $this->versionParser        = $versionParser;
    $this->packagist            = $packagist;

    parent::__construct();
  }
}
