<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use Psr\Http\Message\ResponseInterface;
use Slim\Views\Twig;

final class ListPackagesAction extends AbstractPackageAction {
  protected function action(): ResponseInterface {
    $packages = $this->packageRepository->findPopular();
    $twig = Twig::fromRequest($this->request);

    $this->logger->debug('Packages list was viewed.');

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
        'index.twig',
        [
          'packages' => $packages,
          'app'      => [
            'version' => $_ENV['VERSION']
          ]
        ]
      )
    );
  }
}
