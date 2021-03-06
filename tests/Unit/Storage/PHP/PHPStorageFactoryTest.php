<?php

namespace BenTools\MercurePHP\Tests\Unit\Storage\PHP;

use BenTools\MercurePHP\Storage\PHP\PHPStorage;
use BenTools\MercurePHP\Storage\PHP\PHPStorageFactory;
use React\EventLoop\Factory;

use function Clue\React\Block\await;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

it('supports php scheme', function () {
    $factory = new PHPStorageFactory();
    assertTrue($factory->supports('php://localhost'));
    assertTrue($factory->supports('php://localhost?size=0'));
    assertTrue($factory->supports('php://localhost?size=100'));
});

it('doesn\'t support other schemes', function () {
    $factory = new PHPStorageFactory();
    assertFalse($factory->supports('foo://localhost'));
});

it('creates a storage instance', function () {
    $reflClass = new \ReflectionClass(PHPStorage::class);
    $reflProp = $reflClass->getProperty('size');
    $reflProp->setAccessible(true);

    $loop = Factory::create();
    $factory = new PHPStorageFactory();

    $storage = await($factory->create('php://localhost'), $loop);
    assertInstanceOf(PHPStorage::class, $storage);
    assertEquals(0, $reflProp->getValue($storage));

    $storage = await($factory->create('php://localhost?size=100'), $loop);
    assertInstanceOf(PHPStorage::class, $storage);
    assertEquals(100, $reflProp->getValue($storage));
});
