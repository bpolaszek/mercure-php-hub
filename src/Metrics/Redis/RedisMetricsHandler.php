<?php

namespace BenTools\MercurePHP\Metrics\Redis;

use BenTools\MercurePHP\Metrics\MetricsHandlerInterface;
use Clue\React\Redis\Client as AsynchronousClient;
use React\Promise\PromiseInterface;

use function React\Promise\all;

final class RedisMetricsHandler implements MetricsHandlerInterface
{
    /**
     * @var AsynchronousClient
     */
    private AsynchronousClient $client;

    public function __construct(AsynchronousClient $client)
    {
        $this->client = $client;
    }

    public function resetUsers(string $localAddress): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return $this->client->set('users:' . $localAddress, 0);
    }

    public function incrementUsers(string $localAddress): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return $this->client->incr('users:' . $localAddress);
    }

    public function decrementUsers(string $localAddress): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return $this->client->decr('users:' . $localAddress);
    }

    public function getNbUsers(): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return $this->client->keys('users:*')
            ->then(
                function (array $keys) {
                    $promises = [];
                    foreach ($keys as $key) {
                        $promises[] = $this->client->get($key); /** @phpstan-ignore-line */
                    }

                    return all($promises)->then(fn (array $results): int => \array_sum($results));
                }
            );
    }
}
