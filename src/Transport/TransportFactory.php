<?php

namespace BenTools\MercurePHP\Transport;

use BenTools\MercurePHP\Transport\PHP\PHPTransportFactory;
use BenTools\MercurePHP\Transport\Redis\RedisTransportFactory;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

final class TransportFactory implements TransportFactoryInterface
{
    /**
     * @var TransportFactoryInterface[]
     */
    private array $factories;

    public function __construct(array $factories)
    {
        $this->factories = (fn(TransportFactoryInterface ...$factories) => $factories)(...$factories);
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

        throw new \RuntimeException(\sprintf('Invalid transport DSN %s', $dsn));
    }

    public static function default(array $config, LoggerInterface $logger): self
    {
        return new self(
            [
                new RedisTransportFactory($logger),
                new PHPTransportFactory(),
            ]
        );
    }
}
