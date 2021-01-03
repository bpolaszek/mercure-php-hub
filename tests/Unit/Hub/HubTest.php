<?php

namespace BenTools\MercurePHP\Tests\Unit\Hub;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Hub\Hub;
use BenTools\MercurePHP\Hub\HubFactory;
use BenTools\MercurePHP\Storage\NullStorage\NullStorageFactory;
use BenTools\MercurePHP\Tests\Classes\NullTransport;
use BenTools\MercurePHP\Tests\Classes\NullTransportFactory;
use BenTools\MercurePHP\Transport\TransportFactoryInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Factory;
use React\Promise\PromiseInterface;
use RingCentral\Psr7\ServerRequest;

use function Clue\React\Block\await;
use function PHPUnit\Framework\assertEquals;
use function React\Promise\resolve;

$config = new Configuration(
    [
        Configuration::JWT_KEY => 'foo',
        Configuration::TRANSPORT_URL => 'null://localhost',
        Configuration::STORAGE_URL => 'null://localhost',
        Configuration::METRICS_URL => 'php://localhost',
    ]
);
$transportFactory = new class implements TransportFactoryInterface {
    public function supports(string $dsn): bool
    {
        return true;
    }

    public function create(string $dsn): PromiseInterface
    {
        return resolve(new NullTransport());
    }
};
$loop = Factory::create();
$hub = (new HubFactory(
    $config->asArray(),
    $loop,
    new NullLogger(),
    new NullTransportFactory(),
    new NullStorageFactory()
))->create();


it('returns 200 when asking for health', function () use ($hub) {
    $request = new ServerRequest('GET', '/.well-known/mercure/health');
    $reflClass = new \ReflectionClass(Hub::class);
    $response = await($hub($request), Factory::create());
    assertEquals(200, $response->getStatusCode());
});

it('returns 404 when resource is not found', function () use ($hub) {
    $request = new ServerRequest('GET', '/foo');
    $reflClass = new \ReflectionClass(Hub::class);
    $response = await($hub($request), Factory::create());
    assertEquals(404, $response->getStatusCode());
});

it('returns 403 when not allowed to publish', function () use ($hub) {
    $request = new ServerRequest('POST', '/.well-known/mercure');
    $reflClass = new \ReflectionClass(Hub::class);
    $response = await($hub($request), Factory::create());
    assertEquals(403, $response->getStatusCode());
    assertEquals('Invalid auth token.', (string) $response->getBody());
});

it('returns 403 when not allowed to subscribe', function () use ($hub) {
    $request = new ServerRequest('GET', '/.well-known/mercure');
    $reflClass = new \ReflectionClass(Hub::class);
    $response = await($hub($request), Factory::create());
    assertEquals(403, $response->getStatusCode());
    assertEquals('Anonymous subscriptions are not allowed on this hub.', (string) $response->getBody());
});
