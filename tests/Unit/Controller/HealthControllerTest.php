<?php

namespace BenTools\MercurePHP\Tests\Unit\Controller;

use BenTools\MercurePHP\Controller\HealthController;
use Psr\Http\Message\ResponseInterface;
use RingCentral\Psr7\ServerRequest;

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
    $request = new ServerRequest('GET', '/.well-known/mercure/health');
    $handle = new HealthController();
    $response = $handle($request);
    assertInstanceOf(ResponseInterface::class, $response);
    assertEquals(200, $response->getStatusCode());
    assertEmpty((string) $response->getBody());
});
