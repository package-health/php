<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionNotFoundException;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionCollection;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\HttpCache\CacheProvider;
use Slim\Views\Twig;

final class ListPackageVersionsAction extends AbstractPackageAction {
  private VersionRepositoryInterface $versionRepository;

  public function __construct(
    LoggerInterface $logger,
    CacheProvider $cacheProvider,
    PackageRepositoryInterface $packageRepository,
    VersionRepositoryInterface $versionRepository
  ) {
    parent::__construct($logger, $cacheProvider, $packageRepository);
    $this->versionRepository    = $versionRepository;
  }

  protected function action(): ResponseInterface {
    $vendor  = $this->resolveStringArg('vendor');
    $project = $this->resolveStringArg('project');
    $package = $this->packageRepository->get("{$vendor}/{$project}");
    $twig = Twig::fromRequest($this->request);

    $this->logger->debug("Package '{$vendor}/{$project}' version list was viewed.");

    $taggedCol = $this->versionRepository->find(
      [
        'package_name' => $package->getName(),
        'release' => true
      ]
    );

    if ($taggedCol->isEmpty() === false) {
      $taggedCol = $taggedCol->sort('getCreatedAt', VersionCollection::SORT_DESC);
    }

    $developCol = $this->versionRepository->find(
      [
        'package_name' => $package->getName(),
        'release' => false
      ]
    );

    if ($developCol->isEmpty() === false) {
      $developCol = $developCol->sort('getCreatedAt', VersionCollection::SORT_DESC);
    }

    if (count($taggedCol)) {
      $lastModifiedList = array_map(
        function (Package $package): int {
          $lastModified = $package->getUpdatedAt() ?? $package->getCreatedAt();

          return $lastModified->getTimestamp();
        },
        $taggedCol
      );

      $lastModified = max($lastModifiedList);
      $this->response = $this->cacheProvider->withLastModified(
        $this->response,
        $lastModified
      );
      $this->response = $this->cacheProvider->withEtag(
        $this->response,
        hash('sha1', (string)$lastModified)
      );
    }

    return $this->respondWithHtml(
      $twig->fetch(
        'package/list.twig',
        [
          'package' => $package,
          'tagged' => $taggedCol,
          'develop' => $developCol,
          'app' => [
            'version' => $_ENV['VERSION']
          ]
        ]
      )
    );
  }
}
