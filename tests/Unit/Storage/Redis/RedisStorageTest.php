<?php

namespace BenTools\MercurePHP\Tests\Unit\Storage\Redis;

use BenTools\MercurePHP\Model\Subscription;
use BenTools\MercurePHP\Storage\Redis\RedisStorage;
use BenTools\MercurePHP\Model\Message;
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
    $bucket = await($storage->retrieveMessagesAfterID($ids[0], $subscribedTopics), Factory::create());
    $received = [];
    foreach ($bucket as $topic => $message) {
        $received[] = $message;
    }

    \assertEquals($received, \array_slice($flatten($messages()), 1, 3));

    $subscribedTopics = ['/foo'];
    $bucket = await($storage->retrieveMessagesAfterID($ids[0], $subscribedTopics), Factory::create());
    $received = [];
    foreach ($bucket as $topic => $message) {
        $received[] = $message;
    }

    \assertEquals($received, \array_slice($flatten($messages()), 1, 1));
});

it('stores and retrieves subscriptions', function () {
    $loop = EventLoop\Factory::create();
    $asyncClient = await((new Redis\Factory($loop))->createClient(\getenv('REDIS_DSN')), $loop);
    $syncClient = new Client(\getenv('REDIS_DSN'));
    $storage = new RedisStorage($asyncClient, $syncClient);
    $subscriptions = [
        new Subscription('1', 'Bob', '/foo'),
        new Subscription('2', 'Alice', '/foo'),
        new Subscription('3', 'Bob', '/bar/{any}'),
        new Subscription('4', 'Alice', '/bar/baz'),
    ];

    foreach ($subscriptions as $subscription) {
        $storage->storeSubscriptions([$subscription]);
    }

    // All subscriptions
    $result = \iterable_to_array(await($storage->findSubscriptions(), $loop));
    usort($result, fn(Subscription $a, Subscription $b) => $a->getId() <=> $b->getId());
    \assertEquals($subscriptions, $result);

    // By topic
    $expected = [$subscriptions[2], $subscriptions[3]];
    $result = \iterable_to_array(await($storage->findSubscriptions('/bar/{any}'), $loop));
    usort($result, fn(Subscription $a, Subscription $b) => $a->getId() <=> $b->getId());
    \assertEquals($expected, $result);


    // By subscriber
    $expected = [$subscriptions[0], $subscriptions[2]];
    $result = \iterable_to_array(await($storage->findSubscriptions(null, 'Bob'), $loop));
    usort($result, fn(Subscription $a, Subscription $b) => $a->getId() <=> $b->getId());
    \assertEquals($expected, $result);

    // By topic & subscriber
    $expected = [$subscriptions[2]];
    $result = \iterable_to_array(await($storage->findSubscriptions('/bar/{any}', 'Bob'), $loop));
    usort($result, fn(Subscription $a, Subscription $b) => $a->getId() <=> $b->getId());
    \assertEquals($expected, $result);

    // Remove one
    $storage->removeSubscriptions([$subscriptions[3]]);
    $expected = [$subscriptions[0], $subscriptions[1], $subscriptions[2]];
    $result = \iterable_to_array(await($storage->findSubscriptions(), $loop));
    usort($result, fn(Subscription $a, Subscription $b) => $a->getId() <=> $b->getId());
    \assertEquals($expected, $result);

    $storage->removeSubscriptions($subscriptions);
});
