<?php

namespace BenTools\MercurePHP\Storage\Redis;

use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Model\Message;
use Clue\React\Redis\Client as AsynchronousClient;
use Predis\Client as SynchronousClient;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class RedisStorage implements StorageInterface
{
    private AsynchronousClient $async;
    private SynchronousClient $sync;

    public function __construct(AsynchronousClient $asyncClient, SynchronousClient $syncClient)
    {
        $this->async = $asyncClient;
        $this->sync = $syncClient;
    }

    public function retrieveMessagesAfterId(string $id, array $subscribedTopics): PromiseInterface
    {
        return resolve($this->findNextMessages($id, $subscribedTopics));
    }

    public function storeMessage(string $topic, Message $message): PromiseInterface
    {
        $id = $message->getId();
        $payload = \json_encode($message, \JSON_THROW_ON_ERROR);

        /** @phpstan-ignore-next-line */
        return $this->async->set('data:' . $id, $topic . \PHP_EOL . $payload)
            ->then(fn() => $this->getLastEventId())
            ->then(fn(?string $lastEventId) => $this->storeLastEventId($lastEventId, $id))
            ->then(fn() => $id);
    }

    private function getLastEventId(): PromiseInterface
    {
        return $this->async->get('Last-Event-ID'); /** @phpstan-ignore-line */
    }

    private function storeLastEventId(?string $previousEventId, string $newEventId): PromiseInterface
    {
        $promise = $this->async->set('Last-Event-ID', $newEventId); /** @phpstan-ignore-line */

        if (null === $previousEventId) {
            return $promise;
        }

        /** @phpstan-ignore-next-line */
        return $promise->then(fn() => $this->async->set('next:' . $previousEventId, $newEventId));
    }

    private function findNextMessages(string $id, array $subscribedTopics): iterable
    {
        $nextId = $this->sync->get('next:' . $id);

        /** @phpstan-ignore-next-line */
        if (null === $nextId) {
            return [];
        }

        $payload = $this->sync->get('data:' . $nextId);

        /** @phpstan-ignore-next-line */
        if (null === $payload) {
            return [];
        }

        $item = \explode(\PHP_EOL, $payload);
        $topic = \array_shift($item);
        $message = Message::fromArray(
            \json_decode(
                \implode(\PHP_EOL, $item),
                true,
                512,
                \JSON_THROW_ON_ERROR
            )
        );

        if (TopicMatcher::matchesTopicSelectors($topic, $subscribedTopics)) {
            yield $topic => $message;
        }

        yield from $this->findNextMessages($message->getId(), $subscribedTopics); // Sync client needed because of this
    }
}
