<?php

namespace BenTools\MercurePHP\Tests\Unit\Transport\Redis;

use BenTools\MercurePHP\Transport\Redis\RedisTransport;
use BenTools\MercurePHP\Transport\Redis\RedisTransportFactory;
use BenTools\MercurePHP\Transport\TransportInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Factory;

use function Clue\React\Block\await;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;

it('allows only redis dsns', function (string $dsn, bool $expected) {
    $loop = Factory::create();
    $factory = new RedisTransportFactory($loop, new NullLogger());
    assertEquals($expected, $factory->supports($dsn));
})->with(function () {
    yield ['redis://localhost', true];
    yield ['rediss://localhost', true];
    yield ['http://localhost', false];
    yield ['localhost', false];
    yield ['redis:localhost', false];
});

it('creates an async transport instance', function () {
    $loop = Factory::create();
    $factory = new RedisTransportFactory($loop, new NullLogger());
    $promise = $factory->create('redis://localhost');
    $transport = await($promise, $loop);
    assertInstanceOf(TransportInterface::class, $transport);
    assertInstanceOf(RedisTransport::class, $transport);
});
