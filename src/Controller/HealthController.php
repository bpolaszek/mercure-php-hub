<?php

namespace BenTools\MercurePHP\Controller;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class HealthController extends AbstractController
{
    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        $headers = [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-cache',
        ];

        return resolve(new Response(200, $headers));
    }

    public function matchRequest(RequestInterface $request): bool
    {
        return \in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && '/.well-known/mercure/health' === $request->getUri()->getPath();
    }
}
