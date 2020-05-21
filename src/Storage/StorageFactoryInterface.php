<?php

namespace BenTools\MercurePHP\Storage;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

interface StorageFactoryInterface
{
    /**
     * Returns whether or not a storage could be instanciated by this factory.
     */
    public function supports(string $dsn): bool;

    /**
     * The implementation MUST return a promise which resolves to a StorageInterface implementation.
     */
    public function create(string $dsn, LoopInterface $loop): PromiseInterface;
}
