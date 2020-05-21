<?php

namespace BenTools\MercurePHP\Storage;

use BenTools\MercurePHP\Transport\Message;
use React\Promise\PromiseInterface;

interface StorageInterface
{
    /**
     * The Mercure Hub client can send a Last-Event-ID header to retrieve all messages
     * published AFTER this ID, in the ascending order, in case of a network disruption.
     *
     * The returned Promise MUST yield topic => Message objects, or return an empty iterable.
     */
    public function retrieveMessagesAfterId(string $id): PromiseInterface;

    public function storeMessage(string $topic, Message $message): PromiseInterface;
}
