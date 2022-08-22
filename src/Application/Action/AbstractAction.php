<?php
declare(strict_types = 1);

namespace PackageHealth\PHP\Application\Action;

use PackageHealth\PHP\Domain\Exception\DomainRecordNotFoundException;
use PackageHealth\PHP\Domain\Exception\DomainValidationException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpGoneException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\HttpCache\CacheProvider;

abstract class AbstractAction {
  protected LoggerInterface $logger;
  protected CacheProvider $cacheProvider;
  protected CacheItemPoolInterface $cacheItemPool;
  protected ServerRequestInterface $request;
  protected ResponseInterface $response;

  /**
   * @var array<string, mixed>
   */
  protected array $args;

  public function __construct(
    LoggerInterface $logger,
    CacheProvider $cacheProvider,
    CacheItemPoolInterface $cacheItemPool
  ) {
    $this->logger        = $logger;
    $this->cacheProvider = $cacheProvider;
    $this->cacheItemPool = $cacheItemPool;
  }

  /**
   * @throws \Slim\Exception\HttpNotFoundException
   * @throws \Slim\Exception\HttpBadRequestException
   */
  public function __invoke(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
  ): ResponseInterface {
    $this->request  = $request;
    $this->response = $response;
    $this->args     = $args;

    try {
      return $this->action();
    } catch (DomainValidationException $exception) {
      throw new HttpBadRequestException($this->request, $exception->getMessage());
    } catch (DomainRecordNotFoundException $exception) {
      throw new HttpNotFoundException($this->request, $exception->getMessage());
    }
  }

  /**
   * @throws \PackageHealth\PHP\Domain\DomainException\DomainRecordNotFoundException
   * @throws \Slim\Exception\HttpBadRequestException
   */
  abstract protected function action(): ResponseInterface;

  /**
   * @return array|object
   */
  protected function getFormData() {
    return $this->request->getParsedBody();
  }

  /**
   * @throws \Slim\Exception\HttpBadRequestException
   */
  protected function resolveStringArg(string $name): string {
    if (isset($this->args[$name]) === false) {
      throw new HttpBadRequestException($this->request, "Could not resolve argument '{$name}'.");
    }

    return (string)$this->args[$name];
  }

  /**
   * @throws \Slim\Exception\HttpBadRequestException
   */
  protected function resolveIntArg(string $name): int {
    if (isset($this->args[$name]) === false) {
      throw new HttpBadRequestException($this->request, "Could not resolve argument '{$name}'.");
    }

    return (int)$this->args[$name];
  }

  /**
   * @throws \Slim\Exception\HttpBadRequestException
   */
  protected function resolveFloatArg(string $name): float {
    if (isset($this->args[$name]) === false) {
      throw new HttpBadRequestException($this->request, "Could not resolve argument '{$name}'.");
    }

    return (float)$this->args[$name];
  }

  /**
   * @throws \Slim\Exception\HttpBadRequestException
   */
  protected function resolveBoolArg(string $name): bool {
    if (isset($this->args[$name]) === false) {
      throw new HttpBadRequestException($this->request, "Could not resolve argument '{$name}'.");
    }

    return (bool)$this->args[$name];
  }

  protected function throwError(int $statusCode, string $message = ''): void {
    switch ($statusCode) {
      case 400:
        throw new HttpBadRequestException($this->request);
      case 401:
        throw new HttpUnauthorizedException($this->request);
      case 403:
        throw new HttpForbiddenException($this->request);
      case 404:
        throw new HttpNotFoundException($this->request);
      case 405:
        throw new HttpMethodNotAllowedException($this->request);
      case 410:
        throw new HttpGoneException($this->request);
      case 500:
        throw new HttpInternalServerErrorException($this->request);
      case 501:
        throw new HttpNotImplementedException($this->request);
    }
  }

  protected function respondWith(string $contentType, string $content, int $statusCode = 200): ResponseInterface {
    $this->response->getBody()->write($content);

    return $this->response
      ->withHeader('Content-Type', $contentType)
      ->withStatus($statusCode);
  }

  protected function respondWithHtml(string $content, int $statusCode = 200): ResponseInterface {
    return $this->respondWith('text/html', $content, $statusCode);
  }

  protected function respondWithJson(array $content, int $statusCode = 200): ResponseInterface {
    return $this->respondWith('application/json', json_encode($content, JSON_THROW_ON_ERROR), $statusCode);
  }

  protected function respondWithRedirect(string $url, int $statusCode = 302): ResponseInterface {
    return $this->response
      ->withHeader('Location', $url)
      ->withStatus($statusCode);
  }

  /**
   * @param array|object|null $data
   */
  protected function respondWithData(mixed $data = null, int $statusCode = 200): ResponseInterface {
    $payload = new ActionPayload($statusCode, $data);

    return $this->respond($payload);
  }

  protected function respond(ActionPayload $payload): ResponseInterface {
    $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    $this->response->getBody()->write($json);

    return $this->response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($payload->getStatusCode());
  }
}
