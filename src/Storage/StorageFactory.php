<?php

namespace BenTools\MercurePHP\Storage;

use BenTools\MercurePHP\Storage\InMemory\InMemoryStorageFactory;
use BenTools\MercurePHP\Storage\NullStorage\NullStorageFactory;
use BenTools\MercurePHP\Storage\Redis\RedisStorageFactory;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
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

    public function create(string $dsn, LoopInterface $loop): PromiseInterface
    {
        foreach ($this->factories as $factory) {
            if (!$factory->supports($dsn)) {
                continue;
            }

            return $factory->create($dsn, $loop);
        }

        throw new \RuntimeException(\sprintf('Invalid storage DSN %s', $dsn));
    }

    public static function default(array $config, LoggerInterface $logger): self
    {
        return new self(
            [
                new RedisStorageFactory($logger),
                new InMemoryStorageFactory(),
                new NullStorageFactory(),
            ]
        );
    }
}
