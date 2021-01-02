<?php

namespace BenTools\MercurePHP\Tests\Unit\Transport\PHP;

use BenTools\MercurePHP\Transport\PHP\PHPTransport;
use BenTools\MercurePHP\Transport\PHP\PHPTransportFactory;
use BenTools\MercurePHP\Transport\TransportInterface;
use React\EventLoop\Factory;

use function Clue\React\Block\await;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;

it('allows only redis dsns', function (string $dsn, bool $expected) {
    $factory = new PHPTransportFactory();
    assertEquals($expected, $factory->supports($dsn));
})->with(function () {
    yield ['php://localhost', true];
    yield ['http://localhost', false];
    yield ['localhost', false];
    yield ['php:localhost', false];
});

it('creates a transport instance', function () {
    $factory = new PHPTransportFactory();
    $loop = Factory::create();
    $promise = $factory->create('php://localhost');
    $transport = await($promise, $loop);
    assertInstanceOf(TransportInterface::class, $transport);
    assertInstanceOf(PHPTransport::class, $transport);
});
