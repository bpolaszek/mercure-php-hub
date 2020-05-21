<?php

namespace BenTools\MercurePHP\Tests\Classes;

use BenTools\MercurePHP\Transport\TransportFactoryInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class NullTransportFactory implements TransportFactoryInterface
{

    public function supports(string $dsn): bool
    {
        return 0 === \strpos($dsn, 'null://');
    }

    public function create(string $dsn, LoopInterface $loop): PromiseInterface
    {
        return resolve(new NullTransport());
    }
}
