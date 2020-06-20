<?php

namespace BenTools\MercurePHP\Tests\Classes;

use BenTools\MercurePHP\Message\Message;
use BenTools\MercurePHP\Transport\TransportInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class NullTransport implements TransportInterface
{
    public function publish(string $topic, Message $message): PromiseInterface
    {
        return resolve($message->getId());
    }

    public function subscribe(string $topic, callable $callback): PromiseInterface
    {
        return resolve($topic);
    }

    public function retrieveMessagesAfterId(string $id): PromiseInterface
    {
        return resolve([]);
    }

    public function incrementUsers(): PromiseInterface
    {
        return resolve();
    }

    public function decrementUsers(): PromiseInterface
    {
        return resolve();
    }

    public function getNbUsers(): PromiseInterface
    {
        return resolve(0);
    }
}
