<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Package\PackageNotFoundException;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
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
    PackageRepositoryInterface $packageRepository,
    VersionRepositoryInterface $versionRepository
  ) {
    parent::__construct($logger, $cacheProvider, $packageRepository);
    $this->versionRepository = $versionRepository;
  }

  protected function action(): ResponseInterface {
    $vendor = $this->resolveStringArg('vendor');
    PackageValidator::assertValidVendor($vendor);

    $project = $this->resolveStringArg('project');
    PackageValidator::assertValidProject($project);

    $packageCol = $this->packageRepository->find(
      [
        'name' => "{$vendor}/{$project}"
      ],
      1
    );

    if ($packageCol->isEmpty()) {
      throw new PackageNotFoundException();
    }

    $routeParser = RouteContext::fromRequest($this->request)->getRouteParser();

    $package = $packageCol->first();
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

    return $this->respondWithRedirect(
      $routeParser->urlFor(
        'viewPackage',
        [
          'vendor'  => $vendor,
          'project' => $project,
          'version' => $package->getLatestVersion()
        ]
      )
    );
  }
}
