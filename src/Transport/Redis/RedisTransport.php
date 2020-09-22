<?php

namespace BenTools\MercurePHP\Transport\Redis;

use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Model\Message;
use BenTools\MercurePHP\Transport\TransportInterface;
use Clue\React\Redis\Client as AsynchronousClient;
use React\Promise\PromiseInterface;
use Rize\UriTemplate;

use function React\Promise\resolve;

/**
 * The Redis client cannot handle subscribe and publish within the same connection.
 * It actually needs 2 connections for that purpose.
 */
final class RedisTransport implements TransportInterface
{
    private AsynchronousClient $subscriber;
    private AsynchronousClient $publisher;

    public function __construct(AsynchronousClient $subscriber, AsynchronousClient $publisher)
    {
        $this->subscriber = $subscriber;
        $this->publisher = $publisher;
    }

    public function publish(string $topic, Message $message): PromiseInterface
    {
        $payload = \json_encode($message, \JSON_THROW_ON_ERROR);

        /** @phpstan-ignore-next-line */
        return $this->publisher
            ->publish($topic, $payload);
    }

    public function subscribe(string $topicSelector, callable $callback): PromiseInterface
    {
        // Uri templates
        if (false !== \strpos($topicSelector, '{')) {
            return $this->subscribePattern($topicSelector, $callback);
        }

        /** @phpstan-ignore-next-line */
        $this->subscriber->subscribe($topicSelector);
        $this->subscriber->on(
            'message',
            function (string $topic, string $payload) use ($topicSelector, $callback) {
                $this->dispatch($topic, $payload, $topicSelector, $callback);
            }
        );

        return resolve($topicSelector);
    }

    private function subscribePattern(string $topicSelector, callable $callback): PromiseInterface
    {
        static $uriTemplate;
        $uriTemplate ??= new UriTemplate();
        $keys = \array_keys($uriTemplate->extract($topicSelector, $topicSelector, false));

        // Replaces /author/{author}/books/{book} by /author/*/books/* to match Redis' patterns
        $channel = $uriTemplate->expand(
            $topicSelector,
            \array_combine(
                $keys,
                \array_fill(0, count($keys), '*')
            )
        );
        $channel = \strtr($channel, ['%2A' => '*']);

        /** @phpstan-ignore-next-line */
        $this->subscriber->psubscribe($channel);
        $this->subscriber->on(
            'pmessage',
            function (string $pattern, string $topic, string $payload) use ($topicSelector, $callback) {
                $this->dispatch($topic, $payload, $topicSelector, $callback);
            }
        );

        return resolve($topicSelector);
    }

    private function dispatch(string $topic, string $payload, string $topicSelector, callable $callback): void
    {
        if (!TopicMatcher::matchesTopicSelectors($topic, [$topicSelector])) {
            return;
        }

        $message = Message::fromArray(
            \json_decode(
                $payload,
                true,
                512,
                \JSON_THROW_ON_ERROR
            )
        );

        $callback(
            $topic,
            $message
        );
    }
}
