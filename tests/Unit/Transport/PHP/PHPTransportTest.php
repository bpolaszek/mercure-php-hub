<?php

namespace BenTools\MercurePHP\Tests\Unit\Transport\PHP;

use BenTools\MercurePHP\Model\Message;
use BenTools\MercurePHP\Transport\PHP\PHPTransport;
use React\EventLoop;

use function Clue\React\Block\await;
use function PHPUnit\Framework\assertEquals;

it('transports messages', function () {
    $loop = EventLoop\Factory::create();
    $transport = new PHPTransport();
    $messages = [];
    $onMessage = function (string $topic, Message $message) use (&$messages) {
        $messages[$topic][] = $message;
    };


    await($transport->subscribe('/foo', $onMessage), $loop);
    await($transport->subscribe('/bar/{id}', $onMessage), $loop);
    await($transport->publish('/foo', new Message('bar')), $loop);
    await($transport->publish('/foo/bar', new Message('baz')), $loop);
    await($transport->publish('/bar/baz', new Message('bat')), $loop);

    $expected = [
        '/foo' => [new Message('bar')],
        '/bar/baz' => [new Message('bat')],
    ];

    assertEquals($expected, $messages);
});
