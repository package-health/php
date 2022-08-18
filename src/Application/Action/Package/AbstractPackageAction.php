<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action\Package;

use PackageHealth\PHP\Application\Action\AbstractAction;
use PackageHealth\PHP\Domain\Package\PackageRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Slim\HttpCache\CacheProvider;

abstract class AbstractPackageAction extends AbstractAction {
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
}
