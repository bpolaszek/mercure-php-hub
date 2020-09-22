<?php

namespace BenTools\MercurePHP\Storage;

use BenTools\MercurePHP\Model\Message;
use React\Promise\PromiseInterface;

interface StorageInterface
{
    public const EARLIEST = 'earliest';

    /**
     * The Mercure Hub client can send a Last-Event-ID header to retrieve all messages
     * published AFTER this ID for the subscribed topics, in the ascending order, in case of a network disruption.
     *
     * The returned Promise MUST yield topic => Message objects, or return an empty iterable.
     */
    public function retrieveMessagesAfterId(string $id, array $subscribedTopics): PromiseInterface;

    public function storeMessage(string $topic, Message $message): PromiseInterface;

    public function storeSubscriptions(array $subscriptions): PromiseInterface;

    public function findSubscriptionsBySubscriber(string $subscriber): PromiseInterface;
}
