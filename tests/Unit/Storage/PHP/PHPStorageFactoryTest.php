<?php

namespace BenTools\MercurePHP\Tests\Unit\Storage\PHP;

use BenTools\MercurePHP\Storage\PHP\PHPStorage;
use BenTools\MercurePHP\Storage\PHP\PHPStorageFactory;
use React\EventLoop\Factory;

use function Clue\React\Block\await;

it('supports php scheme', function () {
    $factory = new PHPStorageFactory();
    \assertTrue($factory->supports('php://localhost'));
    \assertTrue($factory->supports('php://localhost?size=0'));
    \assertTrue($factory->supports('php://localhost?size=100'));
});

it('doesn\'t support other schemes', function () {
    $factory = new PHPStorageFactory();
    \assertFalse($factory->supports('foo://localhost'));
});

it('creates a storage instance', function () {
    $reflClass = new \ReflectionClass(PHPStorage::class);
    $reflProp = $reflClass->getProperty('size');
    $reflProp->setAccessible(true);

    $loop = Factory::create();
    $factory = new PHPStorageFactory();

    $storage = await($factory->create('php://localhost', $loop), $loop);
    \assertInstanceOf(PHPStorage::class, $storage);
    \assertEquals(0, $reflProp->getValue($storage));

    $storage = await($factory->create('php://localhost?size=100', $loop), $loop);
    \assertInstanceOf(PHPStorage::class, $storage);
    \assertEquals(100, $reflProp->getValue($storage));
});
