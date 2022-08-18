<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Domain\Package\Package;
use Psr\Http\Message\ResponseInterface;
use Slim\Views\Twig;

final class ListPackagesAction extends AbstractPackageAction {
  protected function action(): ResponseInterface {
    $item = $this->cacheItemPool->getItem('/view/listPackages');
    $html = $item->get();
    if ($item->isHit() === false) {
      $twig = Twig::fromRequest($this->request);

      $packageCol = $this->packageRepository->findPopular();

      $this->logger->debug('Packages list was rendered.');
      $html = $twig->fetch(
        'index.twig',
        [
          'packages' => $packageCol,
          'app'      => [
            'version' => $_ENV['VERSION']
          ]
        ]
      );

      $item->set($html);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    $this->logger->debug('Packages list was viewed.');
    $this->response = $this->cacheProvider->withEtag(
      $this->response,
      hash('sha1', $html)
    );

    return $this->respondWithHtml($html);
  }
}
