<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Dependency\Dependency;
use PackageHealth\PHP\Domain\Dependency\DependencyRepositoryInterface;
use PackageHealth\PHP\Domain\Dependency\DependencyStatusEnum;
use PackageHealth\PHP\Domain\Package\PackageNotFoundException;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionStatusEnum;
use PackageHealth\PHP\Domain\Version\VersionValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use PUGX\Poser\Poser;
use Slim\HttpCache\CacheProvider;

final class ViewPackageBadgeAction extends AbstractPackageAction {
  private Poser $poser;
  private VersionRepositoryInterface $versionRepository;
  private DependencyRepositoryInterface $dependencyRepository;

  public function __construct(
    LoggerInterface $logger,
    CacheProvider $cacheProvider,
    PackageRepositoryInterface $packageRepository,
    Poser $poser,
    VersionRepositoryInterface $versionRepository,
    DependencyRepositoryInterface $dependencyRepository
  ) {
    parent::__construct($logger, $cacheProvider, $packageRepository);

    $this->poser                = $poser;
    $this->versionRepository    = $versionRepository;
    $this->dependencyRepository = $dependencyRepository;
  }

  protected function action(): ResponseInterface {
    $vendor = $this->resolveStringArg('vendor');
    PackageValidator::assertValidVendor($vendor);

    $project = $this->resolveStringArg('project');
    PackageValidator::assertValidProject($project);

    $version = $this->resolveStringArg('version');
    VersionValidator::assertValid($version);

    $packageCol = $this->packageRepository->find(
      [
        'name' => "{$vendor}/{$project}"
      ],
      1
    );

    if ($packageCol->isEmpty()) {
      throw new PackageNotFoundException();
    }

    $this->logger->debug("Status badge for package '{$vendor}/{$project}' was viewed.");

    $package = $packageCol->first();
    $versionCol = $this->versionRepository->find(
      [
        'package_id' => $package->getId(),
        'number'     => $version
      ],
      1
    );

    if ($versionCol->isEmpty()) {
      $badge = $this->poser->generate('dependencies', 'unknown', 'lightgrey', 'flat-square');

      return $this->respondWith('image/svg+xml', (string)$badge);
    }

    $lastModified = $package->getUpdatedAt() ?? $package->getCreatedAt();
    $this->response = $this->cacheProvider->withLastModified(
      $this->response,
      $lastModified->getTimestamp()
    );
    $this->response = $this->cacheProvider->withEtag(
      $this->response,
      hash('sha1', (string)$lastModified->getTimestamp())
    );

    $release = $versionCol->first();

    $status = [
      'text'  => 'unknown',
      'color' => 'lightgrey'
    ];
    switch ($release->getStatus()) {
      case VersionStatusEnum::UpToDate:
        $status = [
          'text'  => 'up-to-date',
          'color' => 'brightgreen'
        ];
        break;

      case VersionStatusEnum::Outdated:
        $dependencyCol = $this->dependencyRepository->find(
          [
            'version_id'  => $release->getId(),
            'development' => false
          ]
        );

        $outdated = count($dependencyCol->filter(
          static function (Dependency $dependency): bool {
            return $dependency->getStatus() === DependencyStatusEnum::Outdated;
          }
        ));

        $total = count($dependencyCol);

        $status = [
          'text'  => "{$outdated} of {$total} outdated",
          'color' => 'yellow'
        ];
        break;

      case VersionStatusEnum::Insecure:
        $status = [
          'text'  => 'insecure',
          'color' => 'red'
        ];
        break;

      case VersionStatusEnum::MaybeInsecure:
        $status = [
          'text'  => 'maybe insecure',
          'color' => 'orange'
        ];
        break;

      case VersionStatusEnum::NoDeps:
        $status = [
          'text'  => 'none',
          'color' => 'blue'
        ];
        break;
    }

    $badge = $this->poser->generate('dependencies', $status['text'], $status['color'], 'flat-square');

    return $this->respondWith('image/svg+xml', (string)$badge);
  }
}
