<?php

namespace BenTools\MercurePHP\Tests\Unit\Storage\Redis;

use BenTools\MercurePHP\Storage\Redis\RedisStorage;
use BenTools\MercurePHP\Storage\Redis\RedisStorageFactory;
use React\EventLoop\Factory;

use function Clue\React\Block\await;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

it('supports redis scheme', function () {
    $loop = Factory::create();
    $factory = new RedisStorageFactory($loop);
    assertTrue($factory->supports('redis://localhost'));
    assertTrue($factory->supports('rediss://localhost'));
    assertTrue($factory->supports('redis://:foobar@localhost'));
    assertTrue($factory->supports('rediss://:foobar@localhost'));
});

it('doesn\'t support other schemes', function () {
    $loop = Factory::create();
    $factory = new RedisStorageFactory($loop);
    assertFalse($factory->supports('foo://localhost'));
});

it('creates a storage instance', function () {
    $loop = Factory::create();
    $factory = new RedisStorageFactory($loop);

    if (!$factory->supports($_SERVER['REDIS_DSN'])) {
        throw new \LogicException('Your Redis DSN is misconfigured in phpunit.xml.');
    }

    $promise = $factory->create($_SERVER['REDIS_DSN']);
    $storage = await($promise, $loop);

    assertInstanceOf(RedisStorage::class, $storage);
});
