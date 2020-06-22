<?php

namespace BenTools\MercurePHP\Tests\Unit\Transport\Redis;

use BenTools\MercurePHP\Message\Message;
use BenTools\MercurePHP\Transport\Redis\RedisTransport;
use Clue\React\Redis;
use React\EventLoop;

use function Clue\React\Block\await;

it('transports messages', function () {

    $loop = EventLoop\Factory::create();
    $subscriberClient = await((new Redis\Factory($loop))->createClient(\getenv('REDIS_DSN')), $loop);
    $publisherClient = await((new Redis\Factory($loop))->createClient(\getenv('REDIS_DSN')), $loop);
    $transport = new RedisTransport($subscriberClient, $publisherClient);
    $messages = [];
    $onMessage = function (string $topic, Message $message) use (&$messages) {
        $messages[$topic][] = $message;
    };

    await($transport->subscribe('/foo', $onMessage), $loop);
    await($transport->subscribe('/bar/{id}', $onMessage), $loop);
    usleep(50000);
    await($transport->publish('/foo', new Message('bar')), $loop);
    await($transport->publish('/foo/bar', new Message('baz')), $loop);
    await($transport->publish('/bar/baz', new Message('bat')), $loop);

    $expected = [
        '/foo' => [new Message('bar')],
        '/bar/baz' => [new Message('bat')],
    ];

    \assertEquals($expected, $messages);
});
