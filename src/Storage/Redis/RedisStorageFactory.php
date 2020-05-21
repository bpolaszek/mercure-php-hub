<?php

namespace BenTools\MercurePHP\Storage\Redis;

use BenTools\MercurePHP\Helpers\LoggerAwareTrait;
use BenTools\MercurePHP\Helpers\RedisHelper;
use BenTools\MercurePHP\Storage\StorageFactoryInterface;
use Clue\React\Redis\Client as AsynchronousClient;
use Clue\React\Redis\Factory;
use Predis\Client as SynchronousClient;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

use function React\Promise\all;
use function React\Promise\resolve;

final class RedisStorageFactory implements StorageFactoryInterface
{
    use LoggerAwareTrait;

    public function __construct(?LoggerInterface $logger = null)
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
        $promises = [
            'async' => $factory->createClient($dsn)
                ->then(
                    function (AsynchronousClient $client) use ($loop) {
                        $client->on(
                            'close',
                            function () use ($loop) {
                                $this->logger()->error('Connection closed.');
                                $loop->stop();
                            }
                        );

                        return $client;
                    },
                    function (\Exception $exception) use ($loop) {
                        $loop->stop();
                        $this->logger()->error($exception->getMessage());
                    }
                ),
            'sync' => resolve(new SynchronousClient($dsn)),
        ];

        return all($promises)
            ->then(
                function (iterable $results) use ($loop): array {
                    $clients = [];
                    foreach ($results as $key => $client) {
                        $clients[$key] = $client;
                    }

                    // Sounds weird, but helps in detecting an anomaly during connection
                    RedisHelper::testAsynchronousClient($clients['async'], $loop, $this->logger());

                    return $clients;
                }
            )
            ->then(
                fn (array $clients): RedisStorage => new RedisStorage(
                    $clients['async'],
                    $clients['sync'],
                )
            );
    }
}
