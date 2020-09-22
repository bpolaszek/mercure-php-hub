<?php

namespace BenTools\MercurePHP\Tests\Unit\Storage\PHP;

use BenTools\MercurePHP\Storage\PHP\PHPStorage;
use BenTools\MercurePHP\Model\Message;
use Ramsey\Uuid\Uuid;
use React\EventLoop\Factory;

use function Clue\React\Block\await;
use function PHPUnit\Framework\assertEquals;

it('won\'t store more messages than the given limit', function (int $size, array $messages, array $expected) {
    $storage = new PHPStorage($size);

    foreach ($messages as $topic => $message) {
        $storage->storeMessage($topic, $message);
    }

    $reflClass = new \ReflectionClass($storage);
    $reflProp = $reflClass->getProperty('messages');
    $reflProp->setAccessible(true);

    $storedMessages = $reflProp->getValue($storage);
    assertEquals($expected, $storedMessages);
})->with(function () {
    $messages = [
        '/foo' => new Message((string) Uuid::uuid4()),
        '/bar' => new Message((string) Uuid::uuid4()),
        '/baz' => new Message((string) Uuid::uuid4()),
        '/bat' => new Message((string) Uuid::uuid4()),
    ];

    $expected = $messages;
    \array_walk($expected, function (Message &$message, string $topic) {
        $message = [$topic, $message];
    });
    $expected = \array_values($expected);

    yield [0, $messages, []];
    yield [100, $messages, $expected];
    yield [3, $messages, \array_slice($expected, 1, 3)];
});

it('retrieves missed messages', function () {
    $storage = new PHPStorage(100);

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
        $storage->storeMessage($topic, $message);
    }

    $subscribedTopics = ['*'];
    $bucket = await($storage->retrieveMessagesAfterId($storage::EARLIEST, $subscribedTopics), Factory::create());
    $received = [];
    foreach ($bucket as $topic => $message) {
        $received[] = $message;
    }

    assertEquals($received, $flatten($messages()));

    $subscribedTopics = ['*'];
    $bucket = await($storage->retrieveMessagesAfterId($ids[0], $subscribedTopics), Factory::create());
    $received = [];
    foreach ($bucket as $topic => $message) {
        $received[] = $message;
    }

    assertEquals($received, \array_slice($flatten($messages()), 1, 3));

    $subscribedTopics = ['/foo'];
    $bucket = await($storage->retrieveMessagesAfterId($storage::EARLIEST, $subscribedTopics), Factory::create());
    $received = [];
    foreach ($bucket as $topic => $message) {
        $received[] = $message;
    }

    assertEquals($received, \array_slice($flatten($messages()), 0, 2));

    $subscribedTopics = ['/foo'];
    $bucket = await($storage->retrieveMessagesAfterId($ids[0], $subscribedTopics), Factory::create());
    $received = [];
    foreach ($bucket as $topic => $message) {
        $received[] = $message;
    }

    assertEquals($received, \array_slice($flatten($messages()), 1, 1));
});
