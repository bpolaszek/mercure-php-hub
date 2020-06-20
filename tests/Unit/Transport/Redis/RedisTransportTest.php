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

    $promise = $transport->subscribe('/foo', $onMessage);
    await($promise, $loop);

    $promise = $transport->publish('/foo', new Message('bar'));
    await($promise, $loop);

    $expected = ['/foo' => [new Message('bar')]];

    \assertEquals($expected, $messages);
});
