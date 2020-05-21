<?php

namespace BenTools\MercurePHP\Helpers;

use Clue\React\Redis\Client as AsynchronousClient;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

final class RedisHelper
{
    public static function testAsynchronousClient(AsynchronousClient $client, LoopInterface $loop, LoggerInterface $logger): void
    {
        /** @phpstan-ignore-next-line */
        $client->get('foo')->then(
            null,
            function (\Exception $e) use ($loop, $logger) {
                $logger->error(\sprintf('Redis error: %s', $e->getMessage()));
                $loop->stop();
            }
        );
    }

    public static function isRedisDSN(string $dsn): bool
    {
        return 0 === strpos($dsn, 'redis://')
            || 0 === strpos($dsn, 'rediss://');
    }
}
