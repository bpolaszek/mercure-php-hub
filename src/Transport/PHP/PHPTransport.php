<?php

namespace BenTools\MercurePHP\Transport\PHP;

use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Message\Message;
use BenTools\MercurePHP\Transport\TransportInterface;
use Evenement\EventEmitter;
use Evenement\EventEmitterInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class PHPTransport implements TransportInterface
{
    private EventEmitterInterface $emitter;

    public function __construct(?EventEmitterInterface $emitter = null)
    {
        $this->emitter = $emitter ?? new EventEmitter();
    }

    public function publish(string $topic, Message $message): PromiseInterface
    {
        $this->emitter->emit('message', [$topic, $message]);
        return resolve($message->getId());
    }

    public function subscribe(string $subscribedTopic, callable $callback): PromiseInterface
    {
        $this->emitter->on('message', function (string $topic, Message $message) use ($callback, $subscribedTopic) {
            if (!TopicMatcher::matchesTopicSelectors($topic, [$subscribedTopic])) {
                return;
            }
            return $callback($topic, $message);
        });
        return resolve($subscribedTopic);
    }
}
