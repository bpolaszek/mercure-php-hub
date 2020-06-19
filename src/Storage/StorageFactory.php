<?php

namespace BenTools\MercurePHP\Storage;

use React\Promise\PromiseInterface;

final class StorageFactory implements StorageFactoryInterface
{
    /**
     * @var StorageFactoryInterface[]
     */
    private array $factories;

    public function __construct(array $factories)
    {
        $this->factories = (fn(StorageFactoryInterface ...$factories) => $factories)(...$factories);
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

        throw new \RuntimeException(\sprintf('Invalid storage DSN %s', $dsn));
    }
}
