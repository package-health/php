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
    PackageRepositoryInterface $packageRepository,
    DependencyRepositoryInterface $dependencyRepository,
    VersionRepositoryInterface $versionRepository
  ) {
    parent::__construct($logger, $cacheProvider, $packageRepository);
    $this->dependencyRepository = $dependencyRepository;
    $this->versionRepository    = $versionRepository;
  }

  protected function action(): ResponseInterface {
    $vendor = $this->resolveStringArg('vendor');
    PackageValidator::assertValidVendor($vendor);

    $project = $this->resolveStringArg('project');
    PackageValidator::assertValidProject($project);

    $version = $this->resolveStringArg('version');
    VersionValidator::assertValid($version);

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

    $this->logger->debug("Package '{$vendor}/{$project}:{$version}' was viewed.");

    $lastModified = $package->getUpdatedAt() ?? $package->getCreatedAt();
    $this->response = $this->cacheProvider->withLastModified(
      $this->response,
      $lastModified->getTimestamp()
    );
    $this->response = $this->cacheProvider->withEtag(
      $this->response,
      hash('sha1', (string)$lastModified->getTimestamp())
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
      'app' => [
        'version' => $_ENV['VERSION']
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
          'text' => $dependency->getStatus()->getLabel()
        ]
      ];

      if (isset($subtitle[$dependency->getStatus()->getLabel()]) === false) {
        $subtitle[$dependency->getStatus()->getLabel()] = 0;
      }

      $subtitle[$dependency->getStatus()->getLabel()]++;
    }

    if (count($subtitle) === 1 && isset($subtitle[DependencyStatusEnum::UpToDate->getLabel()])) {
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
          'text' => $dependency->getStatus()->getLabel()
        ]
      ];

      if (isset($subtitle[$dependency->getStatus()->getLabel()]) === false) {
        $subtitle[$dependency->getStatus()->getLabel()] = 0;
      }

      $subtitle[$dependency->getStatus()->getLabel()]++;
    }

    if (count($subtitle) === 1 && isset($subtitle[DependencyStatusEnum::UpToDate->getLabel()])) {
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

    return $this->respondWithHtml(
      $twig->fetch(
        'package/view.twig',
        $data
      )
    );
  }
}
