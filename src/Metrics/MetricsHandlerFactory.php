<?php

namespace BenTools\MercurePHP\Metrics;

use React\Promise\PromiseInterface;

final class MetricsHandlerFactory implements MetricsHandlerFactoryInterface
{
    /**
     * @var MetricsHandlerFactoryInterface[]
     */
    private array $factories;

    public function __construct(array $factories)
    {
        $this->factories = (fn(MetricsHandlerFactoryInterface ...$factories) => $factories)(...$factories);
    }

    public function supports(string $dsn): bool
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return true;
            }
        }

        return false;
    }

    public function create(string $dsn): PromiseInterface
    {
        foreach ($this->factories as $factory) {
            if (!$factory->supports($dsn)) {
                continue;
            }

            return $factory->create($dsn);
        }

        throw new \RuntimeException(\sprintf('Invalid metrics handler DSN %s', $dsn));
    }
}
