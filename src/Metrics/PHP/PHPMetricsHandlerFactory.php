<?php

namespace BenTools\MercurePHP\Metrics\PHP;

use BenTools\MercurePHP\Metrics\MetricsHandlerFactoryInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class PHPMetricsHandlerFactory implements MetricsHandlerFactoryInterface
{
    public function supports(string $dsn): bool
    {
        return 0 === strpos($dsn, 'php://');
    }

    public function create(string $dsn): PromiseInterface
    {
        return resolve(new PHPMetricsHandler());
    }
}
