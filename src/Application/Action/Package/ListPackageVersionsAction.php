<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Package\PackageNotFoundException;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use PackageHealth\PHP\Domain\Version\Version;
use PackageHealth\PHP\Domain\Version\VersionCollection;
use PackageHealth\PHP\Domain\Version\VersionNotFoundException;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
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
    $vendor = $this->resolveStringArg('vendor');
    PackageValidator::assertValidVendor($vendor);

    $project = $this->resolveStringArg('project');
    PackageValidator::assertValidProject($project);

    $twig = Twig::fromRequest($this->request);

    $packageCol = $this->packageRepository->find(
      [
        'name' => "{$vendor}/{$project}"
      ],
      1
    );

    $package = $packageCol[0] ?? null;
    if ($package === null) {
      throw new PackageNotFoundException();
    }

    $this->logger->debug("Package '{$vendor}/{$project}' version list was viewed.");

    $taggedCol = $this->versionRepository->find(
      [
        'package_id' => $package->getId(),
        'release'    => true
      ]
    );

    if ($taggedCol->isEmpty() === false) {
      $taggedCol = $taggedCol->sort('getCreatedAt', VersionCollection::SORT_DESC);
    }

    $developCol = $this->versionRepository->find(
      [
        'package_id' => $package->getId(),
        'release'    => false
      ]
    );

    if ($developCol->isEmpty() === false) {
      $developCol = $developCol->sort('getCreatedAt', VersionCollection::SORT_DESC);
    }

    if ($taggedCol->count()) {
      $lastModified = array_reduce(
        $taggedCol
          ->map(
            function (Version $version): int {
              $lastModified = $version->getUpdatedAt() ?? $version->getCreatedAt();

              return $lastModified->getTimestamp();
            }
          )
          ->toArray(),
        'max',
        0
      );

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
