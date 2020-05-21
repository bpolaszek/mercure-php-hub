<?php

namespace BenTools\MercurePHP\Tests\Unit\Hub;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Hub\Hub;
use BenTools\MercurePHP\Hub\HubFactory;
use BenTools\MercurePHP\Tests\Classes\NullTransportFactory;
use BenTools\MercurePHP\Transport\TransportFactory;
use Psr\Log\NullLogger;

it('yells if it does not recognize the transport scheme', function () {
    $config = new Configuration(
        [
            Configuration::JWT_KEY => 'foo',
            Configuration::TRANSPORT_URL => 'null://localhost',
        ]
    );
    (new HubFactory($config->asArray(), new NullLogger(), new TransportFactory([])))->create();
})
    ->throws(
        \RuntimeException::class,
        'Invalid transport DSN null://localhost'
    );

it('creates a hub otherwise', function () {
    $config = new Configuration(
        [
            Configuration::JWT_KEY => 'foo',
            Configuration::TRANSPORT_URL => 'null://localhost',
            Configuration::METRICS_URL => 'php://localhost',
        ]
    );
    $hub = (new HubFactory(
        $config->asArray(),
        new NullLogger(),
        new NullTransportFactory()
    ))->create();
    \assertInstanceOf(Hub::class, $hub);
});
