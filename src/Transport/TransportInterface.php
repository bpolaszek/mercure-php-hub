<?php

namespace BenTools\MercurePHP\Transport;

use React\Promise\PromiseInterface;

interface TransportInterface
{
    /**
     * Publishes a message on the transport layer.
     *
     * The returned Promise MUST resolve to the message id.
     */
    public function publish(string $topic, Message $message): PromiseInterface;

    /**
     * Subscribes to a topic.
     * As of the Mercure specification, the topic can be an URI template.
     * When a message comes, the implementation must call $callback with the following arguments:
     * - string $topic - The resolved topic (SHOULD NOT be an URI template)
     * - Message $message - The Message object (instanciated by the implementation).
     *
     * The returned Promise MUST resolve to the topic actually subscribed.
     */
    public function subscribe(string $topic, callable $callback): PromiseInterface;
}
