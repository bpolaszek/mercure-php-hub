<?php

namespace BenTools\MercurePHP\Tests\Unit\Hub;

use BenTools\MercurePHP\Controller\AbstractController;
use BenTools\MercurePHP\Exception\Http\BadRequestHttpException;
use BenTools\MercurePHP\Hub\RequestHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Promise\PromiseInterface;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;

use function Clue\React\Block\await;
use function PHPUnit\Framework\assertSame;
use function React\Promise\resolve;

$controllers = [
    new class extends AbstractController {
        public function __invoke(ServerRequestInterface $request): PromiseInterface
        {
            return resolve(new Response(200, [], 'foo'));
        }

        public function matchRequest(RequestInterface $request): bool
        {
            return 'GET' === $request->getMethod() && '/foo' === $request->getUri()->getPath();
        }
    },
    new class extends AbstractController {
        public function __invoke(ServerRequestInterface $request): PromiseInterface
        {
            return resolve(new Response(200, [], 'bar'));
        }

        public function matchRequest(RequestInterface $request): bool
        {
            return 'POST' === $request->getMethod() && '/foo' === $request->getUri()->getPath();
        }
    },
    new class extends AbstractController {
        public function __invoke(ServerRequestInterface $request): PromiseInterface
        {
            throw new BadRequestHttpException('Nope.');
        }

        public function matchRequest(RequestInterface $request): bool
        {
            return '/bad' === $request->getUri()->getPath();
        }
    },
];

it('calls the appropriate controller', function () use ($controllers) {
    $loop = Factory::create();
    $requestHandler = new RequestHandler($controllers);
    $request = new ServerRequest('POST', '/foo');
    $response = await($requestHandler->handle($request), $loop);
    assertSame(200, $response->getStatusCode());
    assertSame('bar', (string) $response->getBody());
});

it('converts HttpExceptions to response objects', function () use ($controllers) {
    $loop = Factory::create();
    $requestHandler = new RequestHandler($controllers);
    $request = new ServerRequest('POST', '/bad');
    $response = await($requestHandler->handle($request), $loop);
    assertSame(400, $response->getStatusCode());
    assertSame('Nope.', (string) $response->getBody());
});

it('responds 404 when no controller can handle the request', function () use ($controllers) {
    $loop = Factory::create();
    $requestHandler = new RequestHandler($controllers);
    $request = new ServerRequest('POST', '/unknown');
    $response = await($requestHandler->handle($request), $loop);
    assertSame(404, $response->getStatusCode());
    assertSame('Not found.', (string) $response->getBody());
});
