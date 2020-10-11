<?php

namespace BenTools\MercurePHP\Storage\NullStorage;

use BenTools\MercurePHP\Model\Message;
use BenTools\MercurePHP\Model\Subscription;
use BenTools\MercurePHP\Storage\StorageInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class NullStorage implements StorageInterface
{
    public function getLastEventID(): PromiseInterface
    {
        return resolve(null);
    }

    public function retrieveMessagesAfterID(string $id, array $subscribedTopics): PromiseInterface
    {
        return resolve([]);
    }

    public function storeMessage(string $topic, Message $message): PromiseInterface
    {
        return resolve();
    }

    public function storeSubscriptions(array $subscriptions): PromiseInterface
    {
        return resolve();
    }

    public function removeSubscriptions(iterable $subscriptions): PromiseInterface
    {
        return resolve();
    }

    public function findSubscriptions(?string $topic = null, ?string $subscriber = null): PromiseInterface
    {
        return resolve([]);
    }
}
