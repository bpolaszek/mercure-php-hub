<?php

namespace BenTools\MercurePHP\Transport;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

interface TransportFactoryInterface
{
    /**
     * Returns whether or not a transport could be instanciated by this factory.
     */
    public function supports(string $dsn): bool;

    /**
     * The implementation MUST return a promise which resolves to a TransportInterface implementation.
     */
    public function create(string $dsn, LoopInterface $loop): PromiseInterface;
}
