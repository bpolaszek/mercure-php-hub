<?php

namespace BenTools\MercurePHP\Tests\Unit\Hub;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Hub\Hub;
use BenTools\MercurePHP\Hub\HubFactory;
use BenTools\MercurePHP\Metrics\PHP\PHPMetricsHandlerFactory;
use BenTools\MercurePHP\Storage\NullStorage\NullStorageFactory;
use BenTools\MercurePHP\Tests\Classes\NullTransportFactory;
use BenTools\MercurePHP\Tests\Classes\ServicesByTagLocator;
use Psr\Log\NullLogger;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use RingCentral\Psr7\ServerRequest;

use function BenTools\MercurePHP\Tests\container;
use function Clue\React\Block\await;
use function PHPUnit\Framework\assertEquals;

$config = new Configuration(
    [
        Configuration::JWT_KEY => 'foo',
        Configuration::TRANSPORT_URL => 'null://localhost',
        Configuration::STORAGE_URL => 'null://localhost',
        Configuration::METRICS_URL => 'php://localhost',
    ]
);

/** @var Hub $hub */
$hub = (new HubFactory(
    $config,
    container()->get(LoopInterface::class),
    new NullLogger(),
    new NullTransportFactory(),
    new NullStorageFactory(),
    new PHPMetricsHandlerFactory(),
    container()->get(ServicesByTagLocator::class)->getServicesByTag('mercure.controller')
))->create();


it('returns 200 when asking for health', function () use ($hub) {
    $request = new ServerRequest('GET', '/.well-known/mercure/health');
    $response = await($hub($request), Factory::create());
    assertEquals(200, $response->getStatusCode());
});

it('returns 404 when resource is not found', function () use ($hub) {
    $request = new ServerRequest('GET', '/foo');
    $response = await($hub($request), Factory::create());
    assertEquals(404, $response->getStatusCode());
});

it('returns 403 when not allowed to publish', function () use ($hub) {
    $request = new ServerRequest('POST', '/.well-known/mercure');
    $response = await($hub($request), Factory::create());
    assertEquals(403, $response->getStatusCode());
    assertEquals('Invalid auth token.', (string) $response->getBody());
});

it('returns 403 when not allowed to subscribe', function () use ($hub) {
    $request = new ServerRequest('GET', '/.well-known/mercure');
    $response = await($hub($request), Factory::create());
    assertEquals(403, $response->getStatusCode());
    assertEquals('Anonymous subscriptions are not allowed on this hub.', (string) $response->getBody());
});
