<?php

namespace BenTools\MercurePHP\Tests\Unit\Hub;

use BenTools\MercurePHP\Controller\AbstractController;
use BenTools\MercurePHP\Exception\Http\BadRequestHttpException;
use BenTools\MercurePHP\Hub\RequestHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;

use function PHPUnit\Framework\assertSame;

$controllers = [
    new class extends AbstractController {
        public function __invoke(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(200, [], 'foo');
        }

        public function matchRequest(RequestInterface $request): bool
        {
            return 'GET' === $request->getMethod() && '/foo' === $request->getUri()->getPath();
        }
    },
    new class extends AbstractController {
        public function __invoke(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(200, [], 'bar');
        }

        public function matchRequest(RequestInterface $request): bool
        {
            return 'POST' === $request->getMethod() && '/foo' === $request->getUri()->getPath();
        }
    },
    new class extends AbstractController {
        public function __invoke(ServerRequestInterface $request): ResponseInterface
        {
            throw new BadRequestHttpException('Nope.');
        }

        public function matchRequest(RequestInterface $request): bool
        {
            return '/bad' === $request->getUri()->getPath();
        }
    },
];

it(
    'calls the appropriate controller',
    function () use ($controllers) {
        $requestHandler = new RequestHandler($controllers);

        $request = new ServerRequest('POST', '/foo');
        $response = $requestHandler->handle($request);
        assertSame(200, $response->getStatusCode());
        assertSame('bar', (string) $response->getBody());
    }
);

it(
    'converts HttpExceptions to response objects',
    function () use ($controllers) {
        $requestHandler = new RequestHandler($controllers);

        $request = new ServerRequest('POST', '/bad');
        $response = $requestHandler->handle($request);
        assertSame(400, $response->getStatusCode());
        assertSame('Nope.', (string) $response->getBody());
    }
);

it(
    'responds 404 when no controller can handle the request',
    function () use ($controllers) {
        $requestHandler = new RequestHandler($controllers);

        $request = new ServerRequest('POST', '/unknown');
        $response = $requestHandler->handle($request);
        assertSame(404, $response->getStatusCode());
        assertSame('Not found.', (string) $response->getBody());
    }
);
