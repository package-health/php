<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Dependency\DependencyRepositoryInterface;
use PackageHealth\PHP\Domain\Dependency\DependencyStatusEnum;
use PackageHealth\PHP\Domain\Package\PackageNotFoundException;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use PackageHealth\PHP\Domain\Version\VersionNotFoundException;
use PackageHealth\PHP\Domain\Version\VersionRepositoryInterface;
use PackageHealth\PHP\Domain\Version\VersionStatusEnum;
use PackageHealth\PHP\Domain\Version\VersionValidator;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\HttpCache\CacheProvider;
use Slim\Views\Twig;

final class ViewPackageAction extends AbstractPackageAction {
  private DependencyRepositoryInterface $dependencyRepository;
  private VersionRepositoryInterface $versionRepository;

  public function __construct(
    LoggerInterface $logger,
    CacheProvider $cacheProvider,
    CacheItemPoolInterface $cacheItemPool,
    PackageRepositoryInterface $packageRepository,
    DependencyRepositoryInterface $dependencyRepository,
    VersionRepositoryInterface $versionRepository
  ) {
    parent::__construct($logger, $cacheProvider, $cacheItemPool, $packageRepository);
    $this->dependencyRepository = $dependencyRepository;
    $this->versionRepository    = $versionRepository;
  }

  protected function action(): ResponseInterface {
    $vendor = $this->resolveStringArg('vendor');
    PackageValidator::assertValidVendor($vendor);

    $project = $this->resolveStringArg('project');
    PackageValidator::assertValidProject($project);

    $version = $this->resolveStringArg('version');
    VersionValidator::assertValidNumber($version);

    $item = $this->cacheItemPool->getItem("/view/viewPackage/{$vendor}/{$project}/{$version}");
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

      $versionCol = $this->versionRepository->find(
        [
          'package_id' => $package->getId(),
          'number'     => $version
        ],
        1
      );

      if ($versionCol->isEmpty()) {
        throw new VersionNotFoundException(
          sprintf(
            'Version "%s" was not found. Was that release tagged?',
            $version
          )
        );
      }

      $release = $versionCol->first();

      $reqDependencies = $this->dependencyRepository->find(
        [
          'version_id'  => $release->getId(),
          'development' => false
        ]
      );

      $devDependencies = $this->dependencyRepository->find(
        [
          'version_id'  => $release->getId(),
          'development' => true
        ]
      );

      $data = [
        'status' => [
          'type' => ''
        ],
        'package' => $package,
        'version' => $release,
        'requiredDeps' => [],
        'requiredDepsSubtitle' => '',
        'requiredDevDeps' => [],
        'requiredDevDepsSubtitle' => '',
        'show' => [
          'hero' => [
            'footer' => true,
          ],
          'navbar' => [
            'menu' => true
          ]
        ],
        'dates' => [
          'createdAt' => $package->getCreatedAt(),
          'updatedAt' => max($package->getUpdatedAt(), $release->getUpdatedAt())
        ],
        'app' => [
          'canonicalUrl' => (string)$this->request->getUri(),
          'version'      => $_ENV['VERSION']
        ]
      ];

      $data['status']['type'] = match ($release->getStatus()) {
        VersionStatusEnum::UpToDate => 'is-success',
        VersionStatusEnum::NoDeps => 'is-success',
        VersionStatusEnum::Outdated => 'is-warning',
        VersionStatusEnum::Insecure => 'is-danger',
        VersionStatusEnum::MaybeInsecure => 'is-warning',
        VersionStatusEnum::Unknown => 'is-dark'
      };

      $subtitle = [];
      foreach ($reqDependencies as $dependency) {
        $packageCol = $this->packageRepository->find(
          [
            'name' => $dependency->getName()
          ],
          1
        );

        // handle unregistered dependencies
        $unregistered = $packageCol->isEmpty();
        $dependencyPackage = match ($packageCol->isEmpty()) {
          true  => [
            'name' => $dependency->getName()
          ],
          false => $packageCol->first()
        };

        $data['requiredDeps'][] = [
          'package' => $dependencyPackage,
          'requiredVersion' => $dependency->getConstraint(),
          'unregistered' => $unregistered,
          'status' => [
            'type' => $dependency->getStatus()->getColor(),
            'text' => $dependency->getStatus()->value
          ]
        ];

        if (isset($subtitle[$dependency->getStatus()->value]) === false) {
          $subtitle[$dependency->getStatus()->value] = 0;
        }

        $subtitle[$dependency->getStatus()->value]++;
      }

      if (count($subtitle) === 1 && isset($subtitle[DependencyStatusEnum::UpToDate->value])) {
        $data['requiredDepsSubtitle'] = 'all up-to-date';
      } else {
        ksort($subtitle);
        $data['requiredDepsSubtitle'] = implode(
          ', ',
          array_filter(
            array_map(
              static function (string $key, int $value): string {
                return match (DependencyStatusEnum::tryFrom($key)) {
                  DependencyStatusEnum::Unknown       => "{$value} unknown",
                  DependencyStatusEnum::Outdated      => "{$value} outdated",
                  DependencyStatusEnum::Insecure      => "{$value} insecure",
                  DependencyStatusEnum::MaybeInsecure => "{$value} possibly insecure",
                  DependencyStatusEnum::UpToDate      => '', // "{$value} up-to-date"
                  null                                => ''
                };
              },
              array_keys($subtitle),
              array_values($subtitle)
            )
          )
        );
      }

      $subtitle = [];
      foreach ($devDependencies as $dependency) {
        $packageCol = $this->packageRepository->find(
          [
            'name' => $dependency->getName()
          ],
          1
        );

        // handle unregistered dependencies
        $unregistered = $packageCol->isEmpty();
        $dependencyPackage = match ($packageCol->isEmpty()) {
          true  => [
            'name' => $dependency->getName()
          ],
          false => $packageCol->first()
        };

        $data['requiredDevDeps'][] = [
          'package' => $dependencyPackage,
          'requiredVersion' => $dependency->getConstraint(),
          'unregistered' => $unregistered,
          'status' => [
            'type' => $dependency->getStatus()->getColor(),
            'text' => $dependency->getStatus()->value
          ]
        ];

        if (isset($subtitle[$dependency->getStatus()->value]) === false) {
          $subtitle[$dependency->getStatus()->value] = 0;
        }

        $subtitle[$dependency->getStatus()->value]++;
      }

      if (count($subtitle) === 1 && isset($subtitle[DependencyStatusEnum::UpToDate->value])) {
        $data['requiredDevDepsSubtitle'] = 'all up-to-date';
      } else {
        ksort($subtitle);
        $data['requiredDevDepsSubtitle'] = implode(
          ', ',
          array_filter(
            array_map(
              static function (string $key, int $value): string {
                return match (DependencyStatusEnum::tryFrom($key)) {
                  DependencyStatusEnum::Unknown       => "{$value} unknown",
                  DependencyStatusEnum::Outdated      => "{$value} outdated",
                  DependencyStatusEnum::Insecure      => "{$value} insecure",
                  DependencyStatusEnum::MaybeInsecure => "{$value} maybe insecure",
                  DependencyStatusEnum::UpToDate      => '', //"{$value} up-to-date"
                  null                                => ''
                };
              },
              array_keys($subtitle),
              array_values($subtitle)
            )
          )
        );
      }

      $this->logger->debug("Package '{$vendor}/{$project}:{$version}' was rendered.");
      $html = $twig->fetch(
        'package/view.twig',
        $data
      );

      $item->set($html);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    $this->logger->debug("Package '{$vendor}/{$project}:{$version}' was viewed.");
    $this->response = $this->cacheProvider->withEtag(
      $this->response,
      hash('sha1', $html)
    );

    return $this->respondWithHtml($html);
  }
}
