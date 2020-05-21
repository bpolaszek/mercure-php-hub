<?php

namespace BenTools\MercurePHP\Storage\InMemory;

use BenTools\MercurePHP\Storage\StorageFactoryInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use RingCentral\Psr7\Uri;

use function BenTools\QueryString\query_string;
use function React\Promise\resolve;

final class InMemoryStorageFactory implements StorageFactoryInterface
{
    public function supports(string $dsn): bool
    {
        return 0 === \strpos($dsn, 'php://');
    }

    public function create(string $dsn, LoopInterface $loop): PromiseInterface
    {
        $qs = query_string(new Uri($dsn));
        $size = $qs->getParam('size') ?? 0;

        return resolve(new InMemoryStorage((int) $size));
    }
}
