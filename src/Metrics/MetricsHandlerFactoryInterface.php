<?php

namespace BenTools\MercurePHP\Metrics;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

interface MetricsHandlerFactoryInterface
{
    public function supports(string $dsn): bool;

    /**
     * The implementation MUST return a promise which resolves to a MetricsHandlerInterface implementation.
     */
    public function create(string $dsn, LoopInterface $loop): PromiseInterface;
}
