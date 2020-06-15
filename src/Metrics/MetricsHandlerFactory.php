<?php

namespace BenTools\MercurePHP\Metrics;

use BenTools\MercurePHP\Metrics\PHP\PHPMetricsHandlerFactory;
use BenTools\MercurePHP\Metrics\Redis\RedisMetricsHandlerFactory;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
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

    public function create(string $dsn, LoopInterface $loop): PromiseInterface
    {
        foreach ($this->factories as $factory) {
            if (!$factory->supports($dsn)) {
                continue;
            }

            return $factory->create($dsn, $loop);
        }

        throw new \RuntimeException(\sprintf('Invalid metrics handler DSN %s', $dsn));
    }

    public static function default(array $config, LoggerInterface $logger): self
    {
        return new self(
            [
                new PHPMetricsHandlerFactory(),
                new RedisMetricsHandlerFactory($logger),
            ]
        );
    }
}
