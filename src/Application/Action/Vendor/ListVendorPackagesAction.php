<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Vendor;

use PackageHealth\PHP\Application\Action\AbstractAction;
use PackageHealth\PHP\Domain\Package\Package;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\HttpCache\CacheProvider;
use Slim\Views\Twig;

final class ListVendorPackagesAction extends AbstractAction {
  protected PackageRepositoryInterface $packageRepository;

  public function __construct(
    LoggerInterface $logger,
    CacheProvider $cacheProvider,
    PackageRepositoryInterface $packageRepository
  ) {
    parent::__construct($logger, $cacheProvider);
    $this->packageRepository = $packageRepository;
  }

  protected function action(): ResponseInterface {
    $vendor = $this->resolveStringArg('vendor');
    PackageValidator::assertValidVendor($vendor);

    $packageCol = $this->packageRepository->findMatching(['name' => "$vendor/%"]);
    $twig = Twig::fromRequest($this->request);

    $this->logger->debug("Vendor '{$vendor}' package list was viewed.");

    if ($packageCol->count()) {
      $lastModified = array_reduce(
        $packageCol
          ->map(
            function (Package $package): int {
              $lastModified = $package->getUpdatedAt() ?? $package->getCreatedAt();

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
        'vendor/list.twig',
        [
          'vendor'   => $vendor,
          'packages' => $packageCol,
          'app'      => [
            'version' => $_ENV['VERSION']
          ]
        ]
      )
    );
  }
}
