<?php

namespace BenTools\MercurePHP\Storage\NullStorage;

use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Message\Message;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class NullStorage implements StorageInterface
{
    public function retrieveMessagesAfterId(string $id, array $subscribedTopics): PromiseInterface
    {
        return resolve([]);
    }

    public function storeMessage(string $topic, Message $message): PromiseInterface
    {
        return resolve();
    }
}
