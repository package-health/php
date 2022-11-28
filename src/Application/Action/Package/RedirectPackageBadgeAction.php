<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Package\PackageNotFoundException;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use PackageHealth\PHP\Domain\Version\Version;
use PackageHealth\PHP\Domain\Version\VersionNotFoundException;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\HttpCache\CacheProvider;
use Slim\Routing\RouteContext;

final class RedirectPackageBadgeAction extends AbstractPackageAction {
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

    $item = $this->cacheItemPool->getItem("/view/redirectPackageBadge/{$vendor}/{$project}");
    $url  = $item->get();
    if ($item->isHit() === false) {
      $packageCol = $this->packageRepository->find(
        [
          'name' => "{$vendor}/{$project}"
        ],
        1
      );

      if ($packageCol->isEmpty()) {
        throw new PackageNotFoundException();
      }

      $this->logger->debug("Badge for package '{$vendor}/{$project}' is being redirected.");

      $package = $packageCol->first();
      $latestVersion = $package->getLatestVersion();
      if ($latestVersion === '') {
        $versionCol = $this->versionRepository->find(
          query: [
            'package_id' => $package->getId()
          ],
          orderBy: [
            'created_at' => 'DESC'
          ]
        );

        // remove branches that starts with "dev-dependabot/" or "dev-renovate/"
        $versionCol = $versionCol->filter(
          static function (Version $version): bool {
            return str_starts_with($version->getNumber(), 'dev-dependabot/') === false &&
              str_starts_with($version->getNumber(), 'dev-renovate/') === false;
          }
        );

        if ($versionCol->isEmpty() === true) {
          throw new VersionNotFoundException();
        }

        $version = $versionCol->first();
        $latestVersion = $version->getNumber();
      }

      $routeParser = RouteContext::fromRequest($this->request)->getRouteParser();
      $url = $routeParser->urlFor(
        'viewPackageBadge',
        [
          'vendor'  => $vendor,
          'project' => $project,
          'version' => $latestVersion
        ]
      );

      $item->set($url);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $this->respondWithRedirect($url);
  }
}
