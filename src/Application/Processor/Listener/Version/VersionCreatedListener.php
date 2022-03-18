<?php
declare(strict_types = 1);

namespace App\Application\Processor\Listener\Version;

use App\Application\Message\Event\Package\PackageUpdatedEvent;
use App\Domain\Package\PackageRepositoryInterface;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Courier\Client\Producer;
use Courier\Message\EventInterface;
use Courier\Processor\Listener\InvokeListenerInterface;
use Psr\Log\LoggerInterface;

class VersionCreatedListener implements InvokeListenerInterface {
  private PackageRepositoryInterface $packageRepository;
  private VersionParser $versionParser;
  private Producer $producer;
  private LoggerInterface $logger;

  public function __construct(
    PackageRepositoryInterface $packageRepository,
    VersionParser $versionParser,
    Producer $producer,
    LoggerInterface $logger
  ) {
    $this->packageRepository = $packageRepository;
    $this->versionParser     = $versionParser;
    $this->producer          = $producer;
    $this->logger            = $logger;
  }

  /**
   * Checks if the new version is higher than the package's current latest version,
   * if it's, bumps the latest version.
   *
   * Note: only sets the latest version if it is stable
   */
  public function __invoke(EventInterface $event): void {
    $version = $event->getVersion();
    // $this->logger->debug('Version created', [$version]);

    // ignore non-release versions
    if ($version->isRelease() === false) {
      return;
    }

    $package = $this->packageRepository->get(
      $version->getPackageName()
    );

    $latestVersionNormalized = '0.0.0.0';
    $latestVersionIsStable   = false;
    if ($package->getLatestVersion() !== '') {
      $latestVersionNormalized = $this->versionParser->normalize($package->getLatestVersion());
      $latestVersionIsStable   = VersionParser::parseStability($latestVersionNormalized) === 'stable';
    }

    if ($latestVersionIsStable && Comparator::greaterThan($version->getNormalized(), $latestVersionNormalized)) {
      $package = $package->withLatestVersion($version->getNumber());
      $package = $this->packageRepository->update($package);

      $this->producer->sendEvent(
        new PackageUpdatedEvent($package)
      );
    }
  }
}
