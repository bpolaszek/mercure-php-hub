<?php

namespace BenTools\MercurePHP\Tests\Unit\Transport\Redis;

use BenTools\MercurePHP\Model\Message;
use BenTools\MercurePHP\Transport\Redis\RedisTransport;
use Clue\React\Redis;
use React\EventLoop;

use function Clue\React\Block\await;
use function PHPUnit\Framework\assertEquals;
use function React\Promise\all;

it('transports messages', function () {

    dump($_SERVER['REDIS_DSN']);
    $loop = EventLoop\Factory::create();
    $subscriberClient = await((new Redis\Factory($loop))->createClient($_SERVER['REDIS_DSN']), $loop);
    $publisherClient = await((new Redis\Factory($loop))->createClient($_SERVER['REDIS_DSN']), $loop);
    $transport = new RedisTransport($subscriberClient, $publisherClient);
    $messages = [];
    $onMessage = function (string $topic, Message $message) use (&$messages) {
        $messages[$topic][] = $message;
    };

    $subscriptions = [
        $transport->subscribe('/foo', $onMessage),
        $transport->subscribe('/bar/{id}', $onMessage),
    ];

    $promises = all($subscriptions)->then(function () use ($transport) {
        usleep(150000);
        $publications = [
            $transport->publish('/foo', new Message('bar')),
            $transport->publish('/foo/bar', new Message('baz')),
            $transport->publish('/bar/baz', new Message('bat')),
        ];

        return all($publications);
    });

    await($promises, $loop);

    $expected = [
        '/foo' => [new Message('bar')],
        '/bar/baz' => [new Message('bat')],
    ];

    assertEquals($expected, $messages);
});
