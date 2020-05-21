<?php

namespace BenTools\MercurePHP\Storage\Redis;

use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Transport\Message;
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

    public function retrieveMessagesAfterId(string $id): PromiseInterface
    {
        return resolve($this->findNextMessages($id));
    }

    public function storeMessage(string $topic, Message $message): PromiseInterface
    {
        $id = $message->getId();
        $payload = \json_encode($message, \JSON_THROW_ON_ERROR);
        $this->async->set('data:' . $id, $topic . \PHP_EOL . $payload); /** @phpstan-ignore-line */
        /** @phpstan-ignore-next-line */
        $this->async
            ->get('Last-Event-ID')
            ->then(function (?string $lastEventId) use ($id) {
                $this->storeLastEventId($lastEventId, $id);
            });

        return resolve($id);
    }

    private function storeLastEventId(?string $previousEventId, string $newEventId): void
    {
        $this->async->set('Last-Event-ID', $newEventId); /** @phpstan-ignore-line */
        if (null !== $previousEventId) {
            $this->async->set('next:' . $previousEventId, $newEventId); /** @phpstan-ignore-line */
        }
    }

    private function findNextMessages(string $id): iterable
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

        yield $topic => $message;
        yield from $this->findNextMessages($message->getId()); // Sync client needed because of this
    }
}
