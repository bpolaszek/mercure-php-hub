<?php

namespace BenTools\MercurePHP\Tests\Unit\Transport\Redis;

use BenTools\MercurePHP\Transport\Redis\RedisTransport;
use BenTools\MercurePHP\Transport\Redis\RedisTransportFactory;
use BenTools\MercurePHP\Transport\TransportInterface;
use React\EventLoop\Factory;

use function Clue\React\Block\await;

it('allows only redis dsns', function (string $dsn, bool $expected) {
    $factory = new RedisTransportFactory();
    \assertEquals($expected, $factory->supports($dsn));
})->with(function () {
    yield ['redis://localhost', true];
    yield ['rediss://localhost', true];
    yield ['http://localhost', false];
    yield ['localhost', false];
    yield ['redis:localhost', false];
});

it('creates an async transport instance', function () {
    $factory = new RedisTransportFactory();
    $loop = Factory::create();
    $promise = $factory->create('redis://localhost', $loop);
    $transport = await($promise, $loop);
    \assertInstanceOf(TransportInterface::class, $transport);
    \assertInstanceOf(RedisTransport::class, $transport);
});
