<?php
declare(strict_types = 1);

namespace App\Application\Actions;

use App\Domain\DomainException\DomainRecordNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\HttpCache\CacheProvider;

abstract class AbstractAction {
  protected LoggerInterface $logger;

  protected CacheProvider $cacheProvider;

  protected ServerRequestInterface $request;

  protected ResponseInterface $response;

  protected array $args;

  public function __construct(LoggerInterface $logger, CacheProvider $cacheProvider) {
    $this->logger        = $logger;
    $this->cacheProvider = $cacheProvider;
  }

  /**
   * @throws \Slim\Exception\HttpNotFoundException
   * @throws \Slim\Exception\HttpBadRequestException
   */
  public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
    $this->request  = $request;
    $this->response = $response;
    $this->args     = $args;

    try {
      return $this->action();
    } catch (DomainRecordNotFoundException $exception) {
      throw new HttpNotFoundException($this->request, $exception->getMessage());
    }
  }

  /**
   * @throws \App\Domain\DomainException\DomainRecordNotFoundException
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
   * @return mixed
   *
   * @throws \Slim\Exception\HttpBadRequestException
   */
  protected function resolveArg(string $name) {
    if (!isset($this->args[$name])) {
      throw new HttpBadRequestException($this->request, "Could not resolve argument '{$name}'.");
    }

    return $this->args[$name];
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
    return $this->respondWith('application/json', json_encode($content), $statusCode);
  }

  protected function respondWithRedirect(string $url, int $statusCode = 302): ResponseInterface {
    return $this->response
      ->withHeader('Location', $url)
      ->withStatus($statusCode);
  }

  /**
   * @param array|object|null $data
   */
  protected function respondWithData($data = null, int $statusCode = 200): ResponseInterface {
    $payload = new ActionPayload($statusCode, $data);

    return $this->respond($payload);
  }

  protected function respond(ActionPayload $payload): ResponseInterface {
    $json = json_encode($payload, JSON_PRETTY_PRINT);
    $this->response->getBody()->write($json);

    return $this->response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($payload->getStatusCode());
  }
}
