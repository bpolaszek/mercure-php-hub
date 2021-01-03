<?php

namespace BenTools\MercurePHP\Tests\Unit\Model;

use BenTools\MercurePHP\Model\Message;

use function BenTools\CartesianProduct\cartesian_product;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertSame;

$combinations = cartesian_product([
    'id' => [
        'foo',
    ],
    'data' => [
        null,
        'foobar',
        <<<EOF
foo

bar
EOF
,
    ],
    'private' => [
        true,
        false,
    ],
    'type' => [
        null,
        'test',
    ],
    'retry' => [
        null,
        10,
    ],
]);

it('instanciates a message', function (string $id, ?string $data, bool $private, ?string $event, ?int $retry) {
    $message = new Message($id, $data, $private, $event, $retry);
    assertSame($id, $message->getId());
    assertSame($data, $message->getData());
    assertSame($private, $message->isPrivate());
})->with($combinations);

it('produces the expected JSON', function (string $id, ?string $data, bool $private, ?string $event, ?int $retry) {
    $message = new Message($id, $data, $private, $event, $retry);
    assertInstanceOf(\JsonSerializable::class, $message);

    $expected = [
        'id' => $id,
    ];

    if (null !== $data) {
        $expected['data'] = $data;
    }

    $expected['private'] = $private;

    if (null !== $event) {
        $expected['event'] = $event;
    }

    if (null !== $retry) {
        $expected['retry'] = $retry;
    }

    assertSame($expected, $message->jsonSerialize());
})->with($combinations);

it('produces the expected string', function (string $id, ?string $data, bool $private, ?string $event, ?int $retry) {
    $message = new Message($id, $data, $private, $event, $retry);
    assertInstanceOf(\JsonSerializable::class, $message);

    $expected = sprintf('id:%s%s', $id, \PHP_EOL);
    if (null !== $event) {
        $expected .= sprintf('event:%s%s', $event, \PHP_EOL);
    }
    if (null !== $retry) {
        $expected .= sprintf('retry:%d%s', $retry, \PHP_EOL);
    }

    $multiline = <<<EOF
foo

bar
EOF;

    if ($multiline === $data) {
        $expected .= <<<EOF
data:foo
data:
data:bar

EOF;
    }

    if ('foobar' === $data) {
        $expected .= 'data:foobar' . \PHP_EOL;
    }
    $expected .= \PHP_EOL;

    assertSame($expected, (string) $message);
})->with($combinations);
