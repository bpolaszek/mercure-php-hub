<?php

namespace BenTools\MercurePHP\Metrics;

use React\Promise\PromiseInterface;

final class MetricsHandlerFactory implements MetricsHandlerFactoryInterface
{
    /**
     * @var MetricsHandlerFactoryInterface[]
     */
    private iterable $factories;

    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function supports(string $dsn): bool
    {
        foreach ($this->getFactories() as $factory) {
            if ($factory->supports($dsn)) {
                return true;
            }
        }

        return false;
    }

    public function create(string $dsn): PromiseInterface
    {
        foreach ($this->getFactories() as $factory) {
            if (!$factory->supports($dsn)) {
                continue;
            }

            return $factory->create($dsn);
        }

        throw new \RuntimeException(\sprintf('Invalid metrics handler DSN %s', $dsn));
    }

    private function getFactories(): array
    {
        if (\is_array($this->factories)) {
            return $this->factories;
        }

        $factories = [];
        foreach ($this->factories as $factory) {
            if ($factory === $this) {
                continue;
            }
            $factories[] = $factory;
        }
        return $this->factories = (fn(MetricsHandlerFactoryInterface ...$factories) => $factories)(...$factories);
    }
}
