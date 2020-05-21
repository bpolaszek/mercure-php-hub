<?php

namespace BenTools\MercurePHP\Metrics\Redis;

use BenTools\MercurePHP\Helpers\LoggerAwareTrait;
use BenTools\MercurePHP\Helpers\RedisHelper;
use BenTools\MercurePHP\Metrics\MetricsHandlerFactoryInterface;
use Clue\React\Redis\Client as AsynchronousClient;
use Clue\React\Redis\Factory;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

final class RedisMetricsHandlerFactory implements MetricsHandlerFactoryInterface
{
    use LoggerAwareTrait;

    public function __construct(?LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function supports(string $dsn): bool
    {
        return RedisHelper::isRedisDSN($dsn);
    }

    public function create(string $dsn, LoopInterface $loop): PromiseInterface
    {
        $factory = new Factory($loop);

        return $factory->createClient($dsn)
            ->then(fn (AsynchronousClient $client) => new RedisMetricsHandler($client));
    }
}
