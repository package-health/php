<?php
declare(strict_types = 1);

namespace App\Application\Service;

use App\Application\Service\Storage\FileStorageInterface;
use Buzz\Browser;
use Composer\MetadataMinifier\MetadataMinifier;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class Packagist {
  private FileStorageInterface $fileStorage;
  private Browser $browser;

  /**
   * @throws \RuntimeException
   */
  private function updateFileContent(string $file, string $url): string {
    $headers = [
      'User-Agent' => 'php.package.health (twitter.com/flavioheleno)'
    ];

    $fileModTime = 0;
    if ($this->fileStorage->exists($file)) {
      $fileModTime = $this->fileStorage->getModificationTime($file);
    }

    if ($fileModTime > 0) {
      $headers['If-Modified-Since'] = gmdate('D, d M Y H:i:s T', $fileModTime);
    }

    $response = $this->browser->get($url, $headers);
    if ($response->getStatusCode() >= 400) {
      throw new RuntimeException(
        sprintf(
          'Request to "%s" returned status code %d',
          $url,
          $response->getStatusCode()
        )
      );
    }

    // if file has been modified, store the newer version
    if ($response->getStatusCode() !== 304) {
      $content = (string)$response->getBody();

      $this->fileStorage->writeContent($file, $content);
      if ($response->hasHeader('Last-Modified')) {
        $lastModified = strtotime($response->getHeaderLine('Last-Modified'));
        if ($lastModified === false) {
          return $content;
        }

        $this->fileStorage->setModificationTime($file, $lastModified);
      }

      return $content;
    }

    return $this->fileStorage->readContent($file);
  }

  public function __construct(
    FileStorageInterface $fileStorage,
    Browser $browser
  ) {
    $this->fileStorage = $fileStorage;
    $this->browser     = $browser;
  }

  /**
   * @return string[]
   */
  public function getPackageList(string $mirror = 'https://packagist.org'): array {
    $content = $this->updateFileContent(
      'packagist/list.json',
      "${mirror}/packages/list.json"
    );

    $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    if (isset($json['packageNames']) === false) {
      throw new RuntimeException('Invalid package list format');
    }

    return $json['packageNames'];
  }

  public function getPackageMetadataVersion1(
    string $packageName,
    string $mirror = 'https://packagist.org'
  ): array {
    $content = $this->updateFileContent(
      "packagist/${packageName}-v1.json",
      "${mirror}/packages/${packageName}.json"
    );

    $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    if (isset($json['package']) === false) {
      throw new RuntimeException('Invalid package metadata v1 format');
    }

    return $json['package'];
  }

  public function getPackageMetadataVersion2(
    string $packageName,
    string $mirror = 'https://repo.packagist.org'
  ): array {
    $content = $this->updateFileContent(
      "packagist/${packageName}-v2.json",
      "${mirror}/p2/${packageName}.json"
    );

    $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    if (str_ends_with($packageName, '~dev')) {
      $packageName = substr($packageName, 0, strlen($packageName) - 4);
    }

    if (isset($json['packages'][$packageName]) === false) {
      throw new RuntimeException('Invalid package metadata v2 format');
    }

    return MetadataMinifier::expand($json['packages'][$packageName]);
  }

  public function getPackageUpdates(int $since, string $mirror = 'https://packagist.org'): array {
    $content = $this->updateFileContent(
      'packagist/updates.json',
      "${mirror}/metadata/changes.json?since=${since}"
    );

    $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    if (isset($json['actions'], $json['timestamp']) === false) {
      throw new RuntimeException('Invalid package updates format');
    }

    return $json;
  }

  public function getSecurityAdvisories(string $packageName, string $mirror = 'https://packagist.org'): array {
    $content = $this->updateFileContent(
      "packagist/${packageName}-security-advisories.json",
      "${mirror}/api/security-advisories/?packages[]=${packageName}"
    );

    $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    if (isset($json['advisories'][$packageName]) === false) {
      throw new RuntimeException('Invalid security advisories format');
    }

    return $json['advisories'][$packageName];
  }
}
