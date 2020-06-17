<?php

namespace BenTools\MercurePHP\Tests\Unit\Storage\Redis;

use BenTools\MercurePHP\Storage\Redis\RedisStorage;
use BenTools\MercurePHP\Transport\Message;
use Clue\React\Redis;
use Predis\Client;
use Ramsey\Uuid\Uuid;
use React\EventLoop;
use React\EventLoop\Factory;
use function Clue\React\Block\await;

it('retrieves missed messages', function () {

    $loop = EventLoop\Factory::create();
    $asyncClient = await((new Redis\Factory($loop))->createClient(\getenv('REDIS_DSN')), $loop);
    $syncClient = new Client(\getenv('REDIS_DSN'));
    $storage = new RedisStorage($asyncClient, $syncClient);

    $ids = [
        (string) Uuid::uuid4(),
        (string) Uuid::uuid4(),
        (string) Uuid::uuid4(),
        (string) Uuid::uuid4(),
    ];

    $messages = function () use ($ids) {
        yield '/foo' => new Message($ids[0]);
        yield '/foo' => new Message($ids[1]);
        yield '/baz' => new Message($ids[2]);
        yield '/bat' => new Message($ids[3]);
    };

    $flatten = function (iterable $messages): array {
        $values = [];
        foreach ($messages as $message) {
            $values[] = $message;
        }

        return $values;
    };

    foreach ($messages() as $topic => $message) {
        await($storage->storeMessage($topic, $message), $loop);
    }

    $subscribedTopics = ['*'];
    $bucket = await($storage->retrieveMessagesAfterId($ids[0], $subscribedTopics), Factory::create());
    $received = [];
    foreach ($bucket as $topic => $message) {
        $received[] = $message;
    }

    \assertEquals($received, \array_slice($flatten($messages()), 1, 3));

    $subscribedTopics = ['/foo'];
    $bucket = await($storage->retrieveMessagesAfterId($ids[0], $subscribedTopics), Factory::create());
    $received = [];
    foreach ($bucket as $topic => $message) {
        $received[] = $message;
    }

    \assertEquals($received, \array_slice($flatten($messages()), 1, 1));
});
