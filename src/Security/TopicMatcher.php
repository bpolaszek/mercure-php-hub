<?php

namespace BenTools\MercurePHP\Security;

use BenTools\MercurePHP\Message\Message;
use Lcobucci\JWT\Token;
use Rize\UriTemplate;

final class TopicMatcher
{
    public static function matchesTopicSelectors(string $topic, array $topicSelectors): bool
    {
        if (\in_array($topic, $topicSelectors, true)) {
            return true;
        }

        if (\in_array('*', $topicSelectors, true)) {
            return true;
        }

        foreach ($topicSelectors as $topicSelector) {
            if (self::matchesUriTemplate($topic, $topicSelector)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesUriTemplate(string $topic, string $topicSelector): bool
    {
        static $uriTemplate;
        $uriTemplate ??= new UriTemplate();

        return false !== \strpos($topicSelector, '{')
            && null !== $uriTemplate->extract($topicSelector, $topic, true);
    }

    public static function canSubscribeToTopic(string $topic, ?Token $token, bool $allowAnonymous): bool
    {
        if (true === $allowAnonymous) {
            return true;
        }

        if (null === $token) {
            return false;
        }

        try {
            $claim = (array) $token->getClaim('mercure');
        } catch (\OutOfBoundsException $e) {
            return false;
        }

        $allowedTopics = $claim['subscribe'] ?? [];
        $deniedTopics = $claim['subscribe_exclude'] ?? [];

        return self::matchesTopicSelectors($topic, $allowedTopics)
            && !self::matchesTopicSelectors($topic, $deniedTopics);
    }

    public static function canUpdateTopic(string $topic, Token $token, bool $privateUpdate): bool
    {
        try {
            $claim = (array) $token->getClaim('mercure');
        } catch (\OutOfBoundsException $e) {
            return false;
        }

        $allowedTopics = $claim['publish'] ?? null;
        $deniedTopics = $claim['publish_exclude'] ?? [];

        // If not defined, then the publisher MUST NOT be authorized to dispatch any update
        if (null === $allowedTopics || !\is_array($allowedTopics)) {
            return false;
        }

        if (true === $privateUpdate) {
            return self::matchesTopicSelectors($topic, $allowedTopics ?? [])
            && !self::matchesTopicSelectors($topic, $deniedTopics);
        }

        return !self::matchesTopicSelectors($topic, $deniedTopics);
    }

    public static function canReceiveUpdate(
        string $topic,
        Message $message,
        array $subscribedTopics,
        ?Token $token,
        bool $allowAnonymous
    ): bool {
        if (!self::matchesTopicSelectors($topic, $subscribedTopics)) {
            return false;
        }

        if (null === $token && false === $allowAnonymous) {
            return false;
        }

        if (!$message->isPrivate()) {
            return true;
        }

        if (null === $token) {
            return false;
        }

        try {
            $claim = (array) $token->getClaim('mercure');
        } catch (\OutOfBoundsException $e) {
            return false;
        }

        $allowedTopics = $claim['subscribe'] ?? [];
        $deniedTopics = $claim['subscribe_exclude'] ?? [];

        return self::matchesTopicSelectors($topic, $allowedTopics)
            && !self::matchesTopicSelectors($topic, $deniedTopics);
    }
}
