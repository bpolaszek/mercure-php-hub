<?php

namespace BenTools\MercurePHP\Tests\Unit\Model;

use BenTools\MercurePHP\Model\Subscription;
use Ramsey\Uuid\Uuid;

use function BenTools\CartesianProduct\cartesian_product;

$combinations = cartesian_product([
    'active' => [
        null,
        true,
        false,
    ],
    'payload' => [
        null,
        'Me',
        ['username' => 'Bob'],
    ],
]);

it('returns the appropriate subscription object', function (?bool $active, $payload) {
    $id = (string) Uuid::uuid4();
    $subscriber = (string) Uuid::uuid4();
    $subscription = new Subscription(
        $id,
        $subscriber,
        '/foo',
        $payload
    );

    if (null !== $active) {
        $subscription->setActive($active);
    }

    \assertEquals($id, $subscription->getId());
    \assertEquals($subscriber, $subscription->getSubscriber());
    \assertEquals('/foo', $subscription->getTopic());
    \assertEquals($payload, $subscription->getPayload());
    \assertEquals($active ?? true, $subscription->isActive());

    $expectedJson = [
        '@context' => 'https://mercure.rocks/',
        'id' => $id,
        'type' => 'Subscription',
        'subscriber' => $subscriber,
        'topic' => '/foo',
        'active' => $active ?? true,
    ];

    if (null !== $payload) {
        $expectedJson['payload'] = $payload;
    }

    \assertEquals(
        \json_encode($expectedJson, \JSON_THROW_ON_ERROR),
        \json_encode($subscription, \JSON_THROW_ON_ERROR)
    );
})->with($combinations);
