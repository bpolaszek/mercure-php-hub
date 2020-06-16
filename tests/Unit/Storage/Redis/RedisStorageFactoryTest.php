<?php

namespace BenTools\MercurePHP\Tests\Unit\Storage\Redis;

use BenTools\MercurePHP\Storage\Redis\RedisStorage;
use BenTools\MercurePHP\Storage\Redis\RedisStorageFactory;
use React\EventLoop\Factory;

use function Clue\React\Block\await;

it('supports redis scheme', function () {
    $factory = new RedisStorageFactory();
    \assertTrue($factory->supports('redis://localhost'));
    \assertTrue($factory->supports('rediss://localhost'));
    \assertTrue($factory->supports('redis://:foobar@localhost'));
    \assertTrue($factory->supports('rediss://:foobar@localhost'));
});

it('doesn\'t support other schemes', function () {
    $factory = new RedisStorageFactory();
    \assertFalse($factory->supports('foo://localhost'));
});

it('creates a storage instance', function () {
    $loop = Factory::create();
    $factory = new RedisStorageFactory();

    if (!$factory->supports(\getenv('REDIS_DSN'))) {
        throw new \LogicException('Your Redis DSN is misconfigured in phpunit.xml.');
    }

    $promise = $factory->create(\getenv('REDIS_DSN'), $loop);
    $storage = await($promise, $loop);

    \assertInstanceOf(RedisStorage::class, $storage);
});
