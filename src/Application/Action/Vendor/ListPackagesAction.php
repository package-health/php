<?php
declare(strict_types = 1);

namespace App\Application\Action\Vendor;

use App\Application\Action\AbstractAction;
use App\Domain\Package\PackageRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\HttpCache\CacheProvider;
use Slim\Views\Twig;

final class ListPackagesAction extends AbstractAction {
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
    $vendor   = $this->resolveStringArg('vendor');
    $packages = $this->packageRepository->findMatching(['name' => "$vendor/%"]);
    $twig = Twig::fromRequest($this->request);

    $this->logger->debug('Vendor package list was viewed.');

    // if (count($packages)) {
    //   $lastModifiedList = array_map(
    //     function (Package $package): int {
    //       $lastModified = $package->getUpdatedAt() ?? $package->getCreatedAt();

    //       return $lastModified->getTimestamp();
    //     },
    //     $packages
    //   );

    //   $lastModified = max($lastModifiedList);
    //   $this->response = $this->cacheProvider->withLastModified(
    //     $this->response,
    //     $lastModified
    //   );
    //   $this->response = $this->cacheProvider->withEtag(
    //     $this->response,
    //     hash('sha1', (string)$lastModified)
    //   );
    // }

    return $this->respondWithHtml(
      $twig->fetch(
        'vendor/list.twig',
        [
          'vendor'   => $vendor,
          'packages' => $packages,
          'app'      => [
            'version' => $_ENV['VERSION']
          ]
        ]
      )
    );
  }
}
