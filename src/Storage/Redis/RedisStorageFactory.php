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

    private LoopInterface $loop;

    public function __construct(LoopInterface $loop, ?LoggerInterface $logger = null)
    {
        $this->loop = $loop;
        $this->logger = $logger;
    }

    public function supports(string $dsn): bool
    {
        return RedisHelper::isRedisDSN($dsn);
    }

    public function create(string $dsn): PromiseInterface
    {
        $factory = new Factory($this->loop);
        $promises = [
            'async' => $factory->createClient($dsn)
                ->then(
                    function (AsynchronousClient $client) {
                        $client->on(
                            'close',
                            function () {
                                $this->logger()->error('Connection closed.');
                                $this->loop->stop();
                            }
                        );

                        return $client;
                    },
                    function (\Exception $exception) {
                        $this->loop->stop();
                        $this->logger()->error($exception->getMessage());
                    }
                ),
            'sync' => resolve(new SynchronousClient($dsn)),
        ];

        return all($promises)
            ->then(
                function (iterable $results): array {
                    $clients = [];
                    foreach ($results as $key => $client) {
                        $clients[$key] = $client;
                    }

                    // Sounds weird, but helps in detecting an anomaly during connection
                    RedisHelper::testAsynchronousClient($clients['async'], $this->loop, $this->logger());

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
