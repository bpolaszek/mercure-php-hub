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

        $serverParams = [
            'REMOTE_ADDR' => '127.0.0.1',
            'REMOTE_PORT' => 12345,
        ];
        $request = new ServerRequest('POST', '/foo', [], null, '1.1', $serverParams);
        $response = $requestHandler->handle($request);
        \assertEquals(200, $response->getStatusCode());
        \assertEquals('bar', $response->getBody());
    }
);

it(
    'converts HttpExceptions to response objects',
    function () use ($controllers) {
        $requestHandler = new RequestHandler($controllers);

        $serverParams = [
            'REMOTE_ADDR' => '127.0.0.1',
            'REMOTE_PORT' => 12345,
        ];
        $request = new ServerRequest('POST', '/bad', [], null, '1.1', $serverParams);
        $response = $requestHandler->handle($request);
        \assertEquals(400, $response->getStatusCode());
        \assertEquals('Nope.', $response->getBody());
    }
);

it(
    'responds 404 when no controller can handle the request',
    function () use ($controllers) {
        $requestHandler = new RequestHandler($controllers);

        $serverParams = [
            'REMOTE_ADDR' => '127.0.0.1',
            'REMOTE_PORT' => 12345,
        ];
        $request = new ServerRequest('POST', '/unknown', [], null, '1.1', $serverParams);
        $response = $requestHandler->handle($request);
        \assertEquals(404, $response->getStatusCode());
        \assertEquals('Not found.', $response->getBody());
    }
);
