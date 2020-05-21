<?php

namespace BenTools\MercurePHP\Transport\InMemory;

use BenTools\MercurePHP\Transport\Message;
use BenTools\MercurePHP\Transport\TransportInterface;
use Evenement\EventEmitterInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class InMemoryTransport implements TransportInterface
{
    private EventEmitterInterface $emitter;

    public function __construct(EventEmitterInterface $emitter)
    {
        $this->emitter = $emitter;
    }

    public function publish(string $topic, Message $message): PromiseInterface
    {
        $this->emitter->emit($topic, [$message]);
        return resolve($message->getId());
    }

    public function subscribe(string $topic, callable $callback): PromiseInterface
    {
        $this->emitter->on($topic, fn(Message $message) => $callback($topic, $message));
        return resolve($topic);
    }
}
