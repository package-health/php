<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use DateTimeImmutable;
use PackageHealth\PHP\Domain\Package\PackageNotFoundException;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use PackageHealth\PHP\Domain\Version\Version;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\HttpCache\CacheProvider;
use Slim\Views\Twig;

final class ListPackageVersionsAction extends AbstractPackageAction {
  private VersionRepositoryInterface $versionRepository;

  public function __construct(
    LoggerInterface $logger,
    CacheProvider $cacheProvider,
    CacheItemPoolInterface $cacheItemPool,
    PackageRepositoryInterface $packageRepository,
    VersionRepositoryInterface $versionRepository
  ) {
    parent::__construct($logger, $cacheProvider, $cacheItemPool, $packageRepository);
    $this->versionRepository = $versionRepository;
  }

  protected function action(): ResponseInterface {
    $vendor = $this->resolveStringArg('vendor');
    PackageValidator::assertValidVendor($vendor);

    $project = $this->resolveStringArg('project');
    PackageValidator::assertValidProject($project);

    $item = $this->cacheItemPool->getItem("/view/listPackageVersions/{$vendor}/{$project}");
    $html = $item->get();
    if ($item->isHit() === false) {
      $twig = Twig::fromRequest($this->request);

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

      $taggedCol = $this->versionRepository->find(
        query: [
          'package_id' => $package->getId(),
          'release'    => true
        ],
        orderBy: [
          'created_at' => 'DESC'
        ]
      );

      $developCol = $this->versionRepository->find(
        query: [
          'package_id' => $package->getId(),
          'release'    => false
        ],
        orderBy: [
          'created_at' => 'DESC'
        ]
      );

      $this->logger->debug("Package '{$vendor}/{$project}' version list was rendered.");
      $html = $twig->fetch(
        'package/listVersions.twig',
        [
          'package' => $package,
          'tagged'  => $taggedCol,
          'develop' => $developCol,
          'dates'   => [
            'createdAt' => $package->getCreatedAt(),
            'updatedAt' => $taggedCol
              ->merge($developCol)
              ->max(
                static function (Version $version): DateTimeImmutable {
                  return max($version->getCreatedAt(), $version->getUpdatedAt());
                }
              ) ?? new DateTimeImmutable()
          ],
          'app' => [
            'canonicalUrl' => (string)$this->request->getUri(),
            'version'      => $_ENV['VERSION']
          ]
        ]
      );

      $item->set($html);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    $this->logger->debug("Package '{$vendor}/{$project}' version list was viewed.");
    $this->response = $this->cacheProvider->withEtag(
      $this->response,
      hash('sha1', $html)
    );

    return $this->respondWithHtml($html);
  }
}
