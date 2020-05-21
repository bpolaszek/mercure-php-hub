<?php

namespace BenTools\MercurePHP\Storage\InMemory;

use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Transport\Message;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class InMemoryStorage implements StorageInterface
{
    private int $size;
    private int $currentSize = 0;

    /**
     * @var Message[]
     */
    private array $messages = [];

    public function __construct(int $size)
    {
        $this->size = $size;
    }

    public function retrieveMessagesAfterId(string $id): PromiseInterface
    {
        return resolve($this->getMessagesAfterId($id));
    }

    public function storeMessage(string $topic, Message $message): PromiseInterface
    {
        if ($this->currentSize >= $this->size) {
            \array_shift($this->messages);
        }
        $this->messages[] = $message;
        $this->currentSize++;

        return resolve(true);
    }

    private function getMessagesAfterId(string $id): iterable
    {
        $ignore = true;
        foreach ($this->messages as $message) {
            if ($message->getId() === $id) {
                $ignore = false;
                continue;
            }
            if ($ignore) {
                continue;
            }
            yield $message;
        }
    }
}
