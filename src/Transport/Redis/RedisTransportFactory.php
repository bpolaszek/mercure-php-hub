<?php

namespace BenTools\MercurePHP\Transport\Redis;

use BenTools\MercurePHP\Helpers\RedisHelper;
use BenTools\MercurePHP\Transport\TransportFactoryInterface;
use Clue\React\Redis\Client as AsynchronousClient;
use Clue\React\Redis\Factory;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

use function React\Promise\all;

final class RedisTransportFactory implements TransportFactoryInterface
{
    private LoopInterface $loop;
    private LoggerInterface $logger;

    public function __construct(LoopInterface $loop, LoggerInterface $logger)
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
            'subscriber' => $factory->createClient($dsn)
                ->then(
                    function (AsynchronousClient $client) {
                        $client->on(
                            'close',
                            function () {
                                $this->logger->error('Connection closed.');
                                $this->loop->stop();
                            }
                        );

                        return $client;
                    },
                    function (\Exception $exception) {
                        $this->loop->stop();
                        $this->logger->error($exception->getMessage());
                    }
                ),
            'publisher' => $factory->createClient($dsn)
                ->then(
                    function (AsynchronousClient $client) {
                        $client->on(
                            'close',
                            function () {
                                $this->logger->error('Connection closed.');
                                $this->loop->stop();
                            }
                        );

                        return $client;
                    },
                    function (\Exception $exception) {
                        $this->loop->stop();
                        $this->logger->error($exception->getMessage());
                    }
                ),
        ];

        return all($promises)
            ->then(
                function (iterable $results): array {
                    $clients = [];
                    foreach ($results as $key => $client) {
                        $clients[$key] = $client;
                    }

                    RedisHelper::testAsynchronousClient($clients['subscriber'], $this->loop, $this->logger);
                    RedisHelper::testAsynchronousClient($clients['publisher'], $this->loop, $this->logger);

                    return $clients;
                }
            )
            ->then(
                fn (array $clients) => new RedisTransport($clients['subscriber'], $clients['publisher'])
            );
    }
}
