<?php

namespace BenTools\MercurePHP\Controller;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http;

final class HealthController extends AbstractController
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $headers = [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-cache',
        ];

        return new Http\Response(200, $headers);
    }

    public function matchRequest(RequestInterface $request): bool
    {
        return \in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && '/.well-known/mercure/health' === $request->getUri()->getPath();
    }
}
