<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Vendor;

use PackageHealth\PHP\Application\Action\AbstractAction;
use PackageHealth\PHP\Domain\Package\Package;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use PackageHealth\PHP\Domain\Package\PackageValidator;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\HttpCache\CacheProvider;
use Slim\Views\Twig;

final class ListVendorPackagesAction extends AbstractAction {
  protected PackageRepositoryInterface $packageRepository;

  public function __construct(
    LoggerInterface $logger,
    CacheProvider $cacheProvider,
    CacheItemPoolInterface $cacheItemPool,
    PackageRepositoryInterface $packageRepository
  ) {
    parent::__construct($logger, $cacheProvider, $cacheItemPool);
    $this->packageRepository = $packageRepository;
  }

  protected function action(): ResponseInterface {
    $vendor = $this->resolveStringArg('vendor');
    PackageValidator::assertValidVendor($vendor);

    $item = $this->cacheItemPool->getItem("/view/listVendorPackages/{$vendor}");
    $html = $item->get();
    if ($item->isHit() === false) {
      $twig = Twig::fromRequest($this->request);

      $packageCol = $this->packageRepository->findMatching(['name' => "$vendor/%"]);
      if ($packageCol->isEmpty()) {
        $this->throwError(404);
      }

      $this->logger->debug("Vendor '{$vendor}' package list was rendered.");
      $html = $twig->fetch(
        'vendor/list.twig',
        [
          'vendor'   => $vendor,
          'packages' => $packageCol,
          'app'      => [
            'canonicalUrl' => (string)$this->request->getUri(),
            'version' => $_ENV['VERSION']
          ]
        ]
      );

      $item->set($html);
      $item->expiresAfter(3600);

      $this->cacheItemPool->save($item);
    }

    $this->logger->debug("Vendor '{$vendor}' package list was viewed.");
    $this->response = $this->cacheProvider->withEtag(
      $this->response,
      hash('sha1', $html)
    );

    return $this->respondWithHtml($html);
  }
}
