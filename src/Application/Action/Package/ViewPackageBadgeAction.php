<?php
declare(strict_types = 1);

namespace App\Application\Action\Package;

use App\Domain\Dependency\Dependency;
use App\Domain\Dependency\DependencyRepositoryInterface;
use App\Domain\Dependency\DependencyStatusEnum;
use App\Domain\Package\PackageRepositoryInterface;
use App\Domain\Version\VersionRepositoryInterface;
use App\Domain\Version\VersionStatusEnum;
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

  /**
   * {@inheritdoc}
   */
  protected function action(): ResponseInterface {
    $vendor  = $this->resolveStringArg('vendor');
    $project = $this->resolveStringArg('project');
    $version = $this->resolveStringArg('version');
    $package = $this->packageRepository->get("${vendor}/${project}");

    $this->logger->info("Status badge for package '${vendor}/${project}' was viewed.");

    $versionCol = $this->versionRepository->find(
      [
        'number' => $version,
        'package_name' => $package->getName()
      ]
    );

    if ($versionCol->isEmpty()) {
      $badge = $this->poser->generate('dependencies', 'unknown', 'lightgrey', 'flat-square');

      return $this->respondWith('image/svg+xml', (string)$badge);
    }

    // $lastModified = $package->getUpdatedAt() ?? $package->getCreatedAt();
    // $this->response = $this->cacheProvider->withLastModified(
    //   $this->response,
    //   $lastModified->getTimestamp()
    // );
    // $this->response = $this->cacheProvider->withEtag(
    //   $this->response,
    //   hash('sha1', (string)$lastModified->getTimestamp())
    // );

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

        $outdated = $dependencyCol->filter(
          static function (Dependency $dependency): bool {
            return $dependency->getStatus() === DependencyStatusEnum::Outdated;
          }
        )->count();

        $total = $dependencyCol->count();

        $status = [
          'text'  => "${outdated} of ${total} outdated",
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
          'text'  => 'no deps',
          'color' => 'blue'
        ];
        break;
    }

    $badge = $this->poser->generate('dependencies', $status['text'], $status['color'], 'flat-square');

    return $this->respondWith('image/svg+xml', (string)$badge);
  }
}
