<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Package\Package;
use Psr\Http\Message\ResponseInterface;
use Slim\Views\Twig;

final class ListPackagesAction extends AbstractPackageAction {
  protected function action(): ResponseInterface {
    $packageCol = $this->packageRepository->findPopular();
    $twig = Twig::fromRequest($this->request);

    $this->logger->debug('Packages list was viewed.');

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
        'index.twig',
        [
          'packages' => $packageCol,
          'app'      => [
            'version' => $_ENV['VERSION']
          ]
        ]
      )
    );
  }
}
