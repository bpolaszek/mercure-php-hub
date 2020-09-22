<?php

namespace BenTools\MercurePHP\Hub;

use BenTools\MercurePHP\Controller\AbstractController;
use BenTools\MercurePHP\Exception\Http\HttpException;
use BenTools\MercurePHP\Exception\Http\NotFoundHttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use React\Http\Message\Response;

use function BenTools\MercurePHP\get_client_id;
use function BenTools\UriFactory\Helper\uri;

final class RequestHandler implements RequestHandlerInterface
{
    private const CLIENT_NAMESPACE = '530344d8-a802-11ea-bb37-0242ac130002';

    private array $controllers;

    public function __construct(array $controllers)
    {
        $this->controllers = (fn(AbstractController ...$controllers) => $controllers)(...$controllers);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $request = $this->withClientId($request);
            $handle = $this->getController($request);

            return $handle($request);
        } catch (HttpException $e) {
            return new Response(
                $e->getStatusCode(),
                ['Content-Type' => 'text/plain'],
                $e->getMessage(),
            );
        }
    }

    private function getController(ServerRequestInterface $request): AbstractController
    {
        foreach ($this->controllers as $controller) {
            if (!$controller->matchRequest($request)) {
                continue;
            }

            return $controller;
        }

        throw new NotFoundHttpException('Not found.');
    }

    private function withClientId(ServerRequestInterface $request): ServerRequestInterface
    {
        if (null === $request->getAttribute('clientId')) {
            $serverParams = $request->getServerParams();
            $clientId = get_client_id($serverParams['REMOTE_ADDR'], (int) $serverParams['REMOTE_PORT']);
            $request = $request->withAttribute('clientId', $clientId);
        }

        return $request;
    }
}
