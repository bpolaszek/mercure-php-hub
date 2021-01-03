<?php

namespace BenTools\MercurePHP\Tests\Unit\Controller;

use BenTools\MercurePHP\Controller\HealthController;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory;
use RingCentral\Psr7\ServerRequest;

use function Clue\React\Block\await;
use function PHPUnit\Framework\assertEmpty;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

it('responds to the health check endpoint', function () {
    $request = new ServerRequest('GET', '/.well-known/mercure/health');
    $handle = new HealthController();
    assertTrue($handle->matchRequest($request));
});

it('returns a successful response', function () {
    $loop = Factory::create();
    $request = new ServerRequest('GET', '/.well-known/mercure/health');
    $handle = new HealthController();
    $response = await($handle($request), $loop);
    assertInstanceOf(ResponseInterface::class, $response);
    assertEquals(200, $response->getStatusCode());
    assertEmpty((string) $response->getBody());
});
