<?php

namespace BenTools\MercurePHP\Tests\Unit\Transport\Redis;

use BenTools\MercurePHP\Model\Message;
use BenTools\MercurePHP\Transport\Redis\RedisTransport;
use Clue\React\Redis;
use React\EventLoop;

use function Clue\React\Block\await;
use function React\Promise\all;

it('transports messages', function () {

    $loop = EventLoop\Factory::create();
    $subscriberClient = await((new Redis\Factory($loop))->createClient(\getenv('REDIS_DSN')), $loop);
    $publisherClient = await((new Redis\Factory($loop))->createClient(\getenv('REDIS_DSN')), $loop);
    $transport = new RedisTransport($subscriberClient, $publisherClient);
    $messages = [];
    $onMessage = function (string $topic, Message $message) use (&$messages) {
        $messages[$topic][] = $message;
    };

    $loop->futureTick(
        function () use ($transport, $onMessage, $loop) {
            $promises = [
                $transport->subscribe('/foo', $onMessage),
                $transport->subscribe('/bar/{id}', $onMessage),
            ];

            all($promises)->then(
                function () use ($transport, $loop) {
                    $promises = [
                        $transport->publish('/foo', new Message('bar')),
                        $transport->publish('/foo/bar', new Message('baz')),
                        $transport->publish('/bar/baz', new Message('bat')),
                    ];

                    return all($promises)->then(fn() => $loop->stop());
                }
            );
        }
    );

    $loop->run();

    $expected = [
        '/foo' => [new Message('bar')],
        '/bar/baz' => [new Message('bat')],
    ];

    \assertEquals($expected, $messages);
});
