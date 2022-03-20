<?php
declare(strict_types = 1);

namespace App\Application\Console\Packagist;

use App\Application\Message\Event\Dependency\DependencyCreatedEvent;
use App\Application\Message\Event\Dependency\DependencyUpdatedEvent;
use App\Application\Message\Event\Package\PackageUpdatedEvent;
use App\Application\Message\Event\Stats\StatsCreatedEvent;
use App\Application\Message\Event\Stats\StatsUpdatedEvent;
use App\Application\Message\Event\Version\VersionCreatedEvent;
use App\Application\Message\Event\Version\VersionUpdatedEvent;
use App\Application\Service\Packagist;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use App\Domain\Package\PackageRepositoryInterface;
use App\Domain\Stats\StatsRepositoryInterface;
use App\Domain\Version\VersionRepositoryInterface;
use App\Domain\Version\VersionStatusEnum;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Courier\Client\Producer;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GetDataCommand extends Command {
  /**
   * File cache lifetime (12 hour TTL)
   */
  private const FILE_TIMEOUT = 43200;

  protected static $defaultName = 'packagist:get-data';
  private PackageRepositoryInterface $packageRepository;
  private VersionRepositoryInterface $versionRepository;
  private DependencyRepositoryInterface $dependencyRepository;
  private StatsRepositoryInterface $statsRepository;
  private Producer $producer;
  private VersionParser $versionParser;
  private Packagist $packagist;

  /**
   * Command configuration.
   *
   * @return void
   */
  protected function configure(): void {
    $this
      ->setDescription('Get the metadata of a package from a Packagist mirror')
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

      $packageName = $input->getArgument('package');

      $metadata = $this->packagist->getPackageMetadataVersion1($packageName, $mirror);

      $package = $this->packageRepository->get($packageName);
      $package = $package
        ->withDescription($metadata['description'] ?? '')
        ->withUrl($metadata['repository'] ?? '');
      if ($package->isDirty()) {
        $package = $this->packageRepository->update($package);
        $this->producer->sendEvent(
          new PackageUpdatedEvent($package)
        );
      }

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

        if ($stats->isDirty()) {
          $stats = $this->statsRepository->update($stats);
          $this->producer->sendEvent(
            new StatsUpdatedEvent($stats)
          );
        }
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

        $this->producer->sendEvent(
          new StatsCreatedEvent($stats)
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
            'number'       => $release['version'],
            'normalized'   => $release['version_normalized'],
            'package_name' => $packageName,
            'release'      => $isBranch === false
          ]
        );

        $version = $versionCol[0] ?? null;
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
            $release['version'],
            $release['version_normalized'],
            $packageName,
            $isBranch === false,
            VersionStatusEnum::Unknown
          );
          $this->producer->sendEvent(
            new VersionCreatedEvent($version)
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
          $this->producer->sendEvent(
            new VersionUpdatedEvent($version)
          );
        }

        foreach ($filteredRequire as $dependencyName => $constraint) {
          if ($constraint === 'self.version') {
            // need to find out how to handle this
            continue;
          }

          if (isset($packageList[$dependencyName]) === false) {
            $packageList[$dependencyName] = '';
            if ($this->packageRepository->exists($dependencyName)) {
              $packageList[$dependencyName] = $this->packageRepository->get($dependencyName)->getLatestVersion();
            }
          }

          $dependencyCol = $this->dependencyRepository->find(
            [
              'version_id'  => $version->getId(),
              'name'        => $dependencyName,
              'development' => false
            ]
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
            if ($dependency->isDirty()) {
              $dependency = $this->dependencyRepository->update($dependency);
              $this->producer->sendEvent(
                new DependencyUpdatedEvent($dependency)
              );
            }

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
          $this->producer->sendEvent(
            new DependencyCreatedEvent($dependency)
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
            $packageList[$dependencyName] = '';
            if ($this->packageRepository->exists($dependencyName)) {
              $packageList[$dependencyName] = $this->packageRepository->get($dependencyName)->getLatestVersion();
            }
          }

          $dependencyCol = $this->dependencyRepository->find(
            [
              'version_id'  => $version->getId(),
              'name'        => $dependencyName,
              'development' => true
            ]
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
            if ($dependency->isDirty()) {
              $dependency = $this->dependencyRepository->update($dependency);
              $this->producer->sendEvent(
                new DependencyUpdatedEvent($dependency)
              );
            }

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
          $this->producer->sendEvent(
            new DependencyCreatedEvent($dependency)
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
        $this->producer->sendEvent(
          new PackageUpdatedEvent($package)
        );
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
    VersionRepositoryInterface $versionRepository,
    DependencyRepositoryInterface $dependencyRepository,
    StatsRepositoryInterface $statsRepository,
    Producer $producer,
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
