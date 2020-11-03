<?php

namespace BenTools\MercurePHP\Storage;

use BenTools\MercurePHP\Model\Message;
use BenTools\MercurePHP\Model\Subscription;
use React\Promise\PromiseInterface;

interface StorageInterface
{
    public const EARLIEST = 'earliest';

    /**
     * The promise should resolve to the last known event ID,
     * or null otherwise.
     */
    public function getLastEventID(): PromiseInterface;

    /**
     * The Mercure Hub client can send a Last-Event-ID header to retrieve all messages
     * published AFTER this ID for the subscribed topics, in the ascending order, in case of a network disruption.
     *
     * The returned Promise MUST yield topic => Message objects, or return an empty iterable.
     */
    public function retrieveMessagesAfterID(string $id, array $subscribedTopics): PromiseInterface;

    public function storeMessage(string $topic, Message $message): PromiseInterface;

    /**
     * @param Subscription[] $subscriptions
     */
    public function storeSubscriptions(array $subscriptions): PromiseInterface;

    /**
     * @param Subscription[] $subscriptions
     */
    public function removeSubscriptions(iterable $subscriptions): PromiseInterface;

    /**
     * The promise should resolve to an iterable of Subscription objects.
     */
    public function findSubscriptions(?string $topic = null, ?string $subscriber = null): PromiseInterface;
}
