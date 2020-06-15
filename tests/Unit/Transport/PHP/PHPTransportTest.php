<?php

namespace BenTools\MercurePHP\Tests\Unit\Transport\PHP;

use BenTools\MercurePHP\Transport\Message;
use BenTools\MercurePHP\Transport\PHP\PHPTransport;

it('transports messages', function () {
    $transport = new PHPTransport();
    $messages = [];
    $transport->subscribe(
        '/foo',
        function (string $topic, Message $message) use (&$messages) {
            $messages[$topic][] = $message;
        }
    );

    $transport->publish('/foo', new Message('bar'));

    $expected = ['/foo' => [new Message('bar')]];

    \assertEquals($expected, $messages);
});
