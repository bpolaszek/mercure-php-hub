<?php

namespace BenTools\MercurePHP\Metrics\InMemory;

use BenTools\MercurePHP\Metrics\MetricsHandlerFactoryInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class InMemoryMetricsHandlerFactory implements MetricsHandlerFactoryInterface
{
    public function supports(string $dsn): bool
    {
        return 0 === strpos($dsn, 'php://');
    }

    public function create(string $dsn, LoopInterface $loop): PromiseInterface
    {
        return resolve(new InMemoryMetricsHandler());
    }
}
