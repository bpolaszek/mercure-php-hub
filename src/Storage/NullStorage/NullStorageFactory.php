<?php

namespace BenTools\MercurePHP\Storage\NullStorage;

use BenTools\MercurePHP\Storage\StorageFactoryInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class NullStorageFactory implements StorageFactoryInterface
{
    public function supports(string $dsn): bool
    {
        return 0 === \strpos($dsn, 'null://');
    }

    public function create(string $dsn, LoopInterface $loop): PromiseInterface
    {
        return resolve(new NullStorage());
    }
}
