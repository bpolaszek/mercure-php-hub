<?php

namespace BenTools\MercurePHP\Model;

final class Subscription implements \JsonSerializable
{

    private string $id;
    private string $subscriber;
    private string $topic;
    private bool $active = true;
    private $payload;

    public function __construct(string $id, string $subscriber, string $topic, $payload = null)
    {
        $this->id = $id;
        $this->subscriber = $subscriber;
        $this->topic = $topic;
        $this->payload = $payload;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSubscriber(): string
    {
        return $this->subscriber;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function jsonSerialize()
    {
        $subscription = [
            '@context' => 'https://mercure.rocks/',
            'id' => $this->id,
            'type' => 'Subscription',
            'subscriber' => $this->subscriber,
            'topic' => $this->topic,
            'active' => $this->active,
        ];

        if (null !== $this->payload) {
            $subscription['payload'] = $this->payload;
        }

        return $subscription;
    }
}
