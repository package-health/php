<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Package\PackageNotFoundException;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\HttpCache\CacheProvider;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class RedirectPackageAction extends AbstractPackageAction {
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

    $item = $this->cacheItemPool->getItem("/view/redirectPackage/{$vendor}/{$project}");
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

      $package = $packageCol->first();

      $routeParser = RouteContext::fromRequest($this->request)->getRouteParser();

      if ($package->getLatestVersion() === '') {
        $versionCol = $this->versionRepository->find(
          [
            'package_id' => $package->getId()
          ]
        );

        if (count($versionCol)) {
          $release = $versionCol->last();
          $this->logger->debug(
            sprintf(
              'Package "%s" is being redirected to "%s"',
              $package->getName(),
              $release->getNumber()
            )
          );

          return $this->respondWithRedirect(
            $routeParser->urlFor(
              'viewPackage',
              [
                'vendor'  => $vendor,
                'project' => $project,
                'version' => $release->getNumber()
              ]
            )
          );
        }

        $twig = Twig::fromRequest($this->request);

        return $this->respondWithHtml(
          $twig->fetch(
            'package.twig',
            [
              'status'          => [
                'type' => 'is-dark'
              ],
              'package'         => $package,
              'version'         => [],
              'requiredDeps'    => [],
              'requiredDevDeps' => [],
              'notification' => [
                'type' => 'is-warning',
                'message' => 'This package has no public releases.'
              ],
              'show' => [
                'hero' => [
                  'subtitle' => false,
                  'footer'   => false
                ]
              ],
              'app' => [
                'canonicalUrl' => (string)$this->request->getUri(),
                'version' => $_ENV['VERSION']
              ]
            ]
          )
        );
      }

      $this->logger->debug(
        sprintf(
          'Package "%s" is being redirected to "%s"',
          $package->getName(),
          $package->getLatestVersion()
        )
      );
      $url = $routeParser->urlFor(
        'viewPackage',
        [
          'vendor'  => $vendor,
          'project' => $project,
          'version' => $package->getLatestVersion()
        ]
      );

      $item->set($url);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    return $this->respondWithRedirect($url);
  }
}
