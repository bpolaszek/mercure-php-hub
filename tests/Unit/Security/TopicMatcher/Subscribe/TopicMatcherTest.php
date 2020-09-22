<?php

namespace BenTools\MercurePHP\Tests\Unit\Security\TopicMatcher\Subscribe;

use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Tests\Classes\FilterIterator;
use BenTools\MercurePHP\Model\Message;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;

use function BenTools\CartesianProduct\cartesian_product as combinations;

function createMercureJWT(string $key, array $mercureClaim): Token
{
    return (new Builder())
    ->withClaim('mercure', $mercureClaim)
        ->getToken(new Sha256(), new Key($key))
    ;
}

test('the * selector opens all the gates', function () {
    $token = createMercureJWT('foo', ['subscribe' => ['*']]);
    \assertTrue(TopicMatcher::matchesTopicSelectors('/foo', ['*']));
    \assertTrue(TopicMatcher::matchesTopicSelectors('/foo/{bar}', ['*']));
    \assertTrue(TopicMatcher::canSubscribeToTopic('/foo', $token, false));
    \assertTrue(TopicMatcher::canReceiveUpdate('/foo', new Message('foo'), ['/foo'], $token, false));
});

test('some topics can be excluded', function () {
    $token = createMercureJWT('foo', [
        'subscribe' => ['*'],
        'subscribe_exclude' => ['/bar'],
    ]);

    \assertTrue(TopicMatcher::canSubscribeToTopic('/foo', $token, false));
    \assertFalse(TopicMatcher::canSubscribeToTopic('/bar', $token, false));
});

/**
 * All the following tests are:
 * - Subscribe to "/alice", "/bob", "/channels/{whatever}" and "/admins/{id}"
 * - Either anonymous, or JWT allowing "/alice" and "/channels/{whatever}" only.
 */
$combinations = combinations(
    [
        'subscribe' => [
            [
                '/alice',
                '/bob',
                '/channels/{channel}',
                '/admins/{id}',
            ]
        ],
        'allow_anonymous' => [
            true,
            false,
        ],
        'token' => [
            null,
            createMercureJWT('JWT_KEY', [
                'subscribe' => [
                    '/alice',
                    '/channels/{channel}',
                ]
            ]),
        ],
        'private' => [
            false,
            true,
        ],
    ]
);

$counter = 0;

test(
    'When anonymous are allowed and token is null',
    function (array $subscribedTopics, bool $allowAnonymous, ?Token $token, bool $private) use (&$counter) {
        $message = new Message('foo', 'bar', $private);

        \assertTrue(TopicMatcher::canSubscribeToTopic($subscribedTopics[0], $token, $allowAnonymous));
        \assertTrue(TopicMatcher::canSubscribeToTopic($subscribedTopics[1], $token, $allowAnonymous));
        $privateRecipients = [
            '/alice' => false, // No token, no updates
            '/bob' => false, // No token, no updates
            '/unknown' => false, // Not subscribed
            '/channels/foo' => false, // No token, no updates
            '/admins/1' => false, // No token, no updates
        ];
        $publicRecipients = [
            '/alice' => true, // Subscribed
            '/bob' => true, // Subscribed
            '/unknown' => false, // Not subscribed
            '/channels/foo' => true, // Subscribed via template
            '/admins/1' => true, // Subscribed via template
        ];
        $recipients = $message->isPrivate() ? $privateRecipients : $publicRecipients;
        foreach ($recipients as $topic => $expected) {
            \assertEquals(
                $expected,
                TopicMatcher::canReceiveUpdate($topic, $message, $subscribedTopics, $token, $allowAnonymous)
            );
        }

        $counter++;
    }
)
    ->with(
        (new FilterIterator($combinations))
            ->filter(fn ($combination) => true === $combination['allow_anonymous'])
            ->filter(fn ($combination) => null === $combination['token'])
    );

test(
    'When anonymous are allowed and token is valid',
    function (array $subscribedTopics, bool $allowAnonymous, ?Token $token, bool $private) use (&$counter) {
        $message = new Message('foo', 'bar', $private);

        \assertTrue(TopicMatcher::canSubscribeToTopic($subscribedTopics[0], $token, $allowAnonymous));
        \assertTrue(TopicMatcher::canSubscribeToTopic($subscribedTopics[1], $token, $allowAnonymous));
        $privateRecipients = [
            '/alice' => true, // Token allows this topic
            '/bob' => false, // Token doesn't allow this topic
            '/unknown' => false, // Not subscribed
            '/channels/foo' => true, // URI template matches token claim
            '/admins/1' => false, // URI template doesn't match token claim
        ];
        $publicRecipients = [
            '/alice' => true, // Subscribed
            '/bob' => true, // Subscribed
            '/unknown' => false, // Not subscribed
            '/channels/foo' => true, // Subscribed via template
            '/admins/1' => true, // Subscribed via template
        ];
        $recipients = $message->isPrivate() ? $privateRecipients : $publicRecipients;
        foreach ($recipients as $topic => $expected) {
            \assertEquals(
                $expected,
                TopicMatcher::canReceiveUpdate($topic, $message, $subscribedTopics, $token, $allowAnonymous)
            );
        }
        $counter++;
    }
)
    ->with(
        (new FilterIterator($combinations))
            ->filter(fn ($combination) => true === $combination['allow_anonymous'])
            ->filter(fn ($combination) => null !== $combination['token'])
    );

test(
    'When anonymous are not allowed and token is null',
    function (array $subscribedTopics, bool $allowAnonymous, ?Token $token, bool $private) use (&$counter) {
        $message = new Message('foo', 'bar', $private);

        \assertFalse(TopicMatcher::canSubscribeToTopic($subscribedTopics[0], $token, $allowAnonymous));
        \assertFalse(TopicMatcher::canSubscribeToTopic($subscribedTopics[1], $token, $allowAnonymous));
        $privateRecipients = [
            '/alice' => false, // No token, no updates
            '/bob' => false, // No token, no updates
            '/unknown' => false, // Not subscribed
            '/channels/foo' => false, // No token, no updates
            '/admins/1' => false, // No token, no updates
        ];
        $publicRecipients = [
            '/alice' => false, // No token, no updates
            '/bob' => false, // No token, no updates
            '/unknown' => false, // Not subscribed
            '/channels/foo' => false, // No token, no updates
            '/admins/1' => false, // No token, no updates
        ];
        $recipients = $message->isPrivate() ? $privateRecipients : $publicRecipients;
        foreach ($recipients as $topic => $expected) {
            \assertEquals(
                $expected,
                TopicMatcher::canReceiveUpdate($topic, $message, $subscribedTopics, $token, $allowAnonymous)
            );
        }
        $counter++;
    }
)
    ->with(
        (new FilterIterator($combinations))
            ->filter(fn ($combination) => false === $combination['allow_anonymous'])
            ->filter(fn ($combination) => null === $combination['token'])
    );

test(
    'When anonymous are not allowed and token is valid',
    function (array $subscribedTopics, bool $allowAnonymous, ?Token $token, bool $private) use (&$counter) {
        $message = new Message('foo', 'bar', $private);

        \assertTrue(TopicMatcher::canSubscribeToTopic($subscribedTopics[0], $token, $allowAnonymous));
        \assertFalse(TopicMatcher::canSubscribeToTopic($subscribedTopics[1], $token, $allowAnonymous));

        $privateRecipients = [
            '/alice' => true, // Subscribed, authorized
            '/bob' => false, // Subscribed, not authorized
            '/unknown' => false, // Not subscribed
            '/channels/foo' => true, // Subscribed via template
            '/admins/1' => false, // Subscribed, but not authorized
        ];
        $publicRecipients = [
            '/alice' => true, // Subscribed, authorized
            '/bob' => true, // Subscribed, not authorized but public
            '/unknown' => false, // Not subscribed
            '/channels/foo' => true, // Subscribed via template
            '/admins/1' => true, // Subscribed via template
        ];
        $recipients = $message->isPrivate() ? $privateRecipients : $publicRecipients;
        foreach ($recipients as $topic => $expected) {
            \assertEquals(
                $expected,
                TopicMatcher::canReceiveUpdate($topic, $message, $subscribedTopics, $token, $allowAnonymous)
            );
        }
        $counter++;
    }
)
    ->with(
        (new FilterIterator($combinations))
            ->filter(fn ($combination) => false === $combination['allow_anonymous'])
            ->filter(fn ($combination) => null !== $combination['token'])
    );

test('all combinations have been tested', function () use ($combinations, &$counter) {
    \assertEquals(\count($combinations), $counter);
});
