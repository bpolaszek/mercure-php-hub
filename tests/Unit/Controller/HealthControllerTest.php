<?php

namespace BenTools\MercurePHP\Tests\Unit\Controller;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Controller\HealthController;
use BenTools\MercurePHP\Tests\Classes\NullTransport;
use Psr\Http\Message\ResponseInterface;
use RingCentral\Psr7\ServerRequest;

it('responds to the health check endpoint', function () {
    $transport = new NullTransport();
    $config = [Configuration::CORS_ALLOWED_ORIGINS => '*'];
    $request = new ServerRequest('GET', '/.well-known/mercure/health');
    $handle = new HealthController();
    \assertTrue($handle->matchRequest($request));
});

it('returns a successful response', function () {
    $config = [Configuration::CORS_ALLOWED_ORIGINS => '*'];
    $request = new ServerRequest('GET', '/.well-known/mercure/health');
    $handle = new HealthController();
    $response = $handle($request);
    \assertInstanceOf(ResponseInterface::class, $response);
    \assertEquals(200, $response->getStatusCode());
    \assertEmpty((string) $response->getBody());
});
