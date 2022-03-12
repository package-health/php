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
   */
  public function __invoke(EventInterface $event): void {
    $version = $event->getVersion();
    $this->logger->debug('Version created', [$version]);

    // ignore non-release versions
    if ($version->isRelease() === false) {
      return;
    }

    $package = $this->packageRepository->get(
      $version->getPackageName()
    );

    $latestNormalizedVersion = '0.0.0.0';
    if ($package->getLatestVersion() !== '') {
      $latestNormalizedVersion = $this->versionParser->normalize($package->getLatestVersion());
    }

    if (Comparator::greaterThan($version->getNormalized(), $latestNormalizedVersion)) {
      $package = $package->withLatestVersion($version->getNumber());
      $package = $this->packageRepository->update($package);

      $this->producer->sendEvent(
        new PackageUpdatedEvent($package)
      );
    }
  }
}
