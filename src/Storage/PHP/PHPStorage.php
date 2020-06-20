<?php

namespace BenTools\MercurePHP\Storage\PHP;

use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Message\Message;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class PHPStorage implements StorageInterface
{
    private int $size;
    private int $currentSize = 0;

    /**
     * @var array
     */
    private array $messages = [];

    public function __construct(int $size)
    {
        $this->size = $size;
    }

    public function retrieveMessagesAfterId(string $id, array $subscribedTopics): PromiseInterface
    {
        if (self::EARLIEST === $id) {
            return resolve($this->getAllMessages($subscribedTopics));
        }

        return resolve($this->getMessagesAfterId($id, $subscribedTopics));
    }

    public function storeMessage(string $topic, Message $message): PromiseInterface
    {
        if (0 === $this->size) {
            return resolve(true);
        }

        if ($this->currentSize >= $this->size) {
            \array_shift($this->messages);
        }
        $this->messages[] = [$topic, $message];
        $this->currentSize++;

        return resolve(true);
    }

    private function getMessagesAfterId(string $id, array $subscribedTopics): iterable
    {
        $ignore = true;
        foreach ($this->messages as [$topic, $message]) {
            if ($message->getId() === $id) {
                $ignore = false;
                continue;
            }
            if ($ignore || !TopicMatcher::matchesTopicSelectors($topic, $subscribedTopics)) {
                continue;
            }
            yield $topic => $message;
        }
    }

    private function getAllMessages(array $subscribedTopics): iterable
    {
        foreach ($this->messages as [$topic, $message]) {
            if (!TopicMatcher::matchesTopicSelectors($topic, $subscribedTopics)) {
                continue;
            }
            yield $topic => $message;
        }
    }
}
