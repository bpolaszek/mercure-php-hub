<?php

namespace BenTools\MercurePHP\Metrics\Redis;

use BenTools\MercurePHP\Helpers\RedisHelper;
use BenTools\MercurePHP\Metrics\MetricsHandlerFactoryInterface;
use Clue\React\Redis\Client as AsynchronousClient;
use Clue\React\Redis\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

final class RedisMetricsHandlerFactory implements MetricsHandlerFactoryInterface
{
    private LoopInterface $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function supports(string $dsn): bool
    {
        return RedisHelper::isRedisDSN($dsn);
    }

    public function create(string $dsn): PromiseInterface
    {
        $factory = new Factory($this->loop);

        return $factory->createClient($dsn)
            ->then(fn (AsynchronousClient $client) => new RedisMetricsHandler($client));
    }
}
