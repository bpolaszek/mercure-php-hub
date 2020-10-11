<?php

namespace BenTools\MercurePHP\Controller;

use BenTools\MercurePHP\Exception\Http\AccessDeniedHttpException;
use BenTools\MercurePHP\Hub\Hub;
use BenTools\MercurePHP\Model\Subscription;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Storage\StorageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Stream\ThroughStream as Stream;

use function BenTools\MercurePHP\nullify;

final class SubscriptionsController extends AbstractController
{
    private const PATH = '/.well-known/mercure/subscriptions';
    private const TOPIC_SELECTOR = '/.well-known/mercure/subscriptions/{topic}';
    private const TOPIC_AND_SUBSCRIBER_SELECTOR = '/.well-known/mercure/subscriptions/{topic}/{subscriber}';
    private Hub $hub;
    private Authenticator $authenticator;

    public function __construct(Hub $hub, Authenticator $authenticator)
    {
        $this->hub = $hub;
        $this->authenticator = $authenticator;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!\in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'])) {
            return new Response(405);
        }

        $path = $request->getUri()->getPath();
        [$topicFilter, $subscriberFilter] = $this->extractFilters($request);
        $token = $this->authenticator->authenticate($request);

        if (null === $token) {
            throw new AccessDeniedHttpException('You must be authenticated to access the Subscription API.');
        }

        $claim = (array) $token->getClaim('mercure');
        $allowedTopics = $claim['subscribe'] ?? [];
        $deniedTopics = $claim['subscribe_exclude'] ?? [];
        $matchAllowedTopics = TopicMatcher::matchesTopicSelectors($path, $allowedTopics);
        $matchDeniedTopics = TopicMatcher::matchesTopicSelectors($path, $deniedTopics);
        if (!$matchAllowedTopics || $matchDeniedTopics) {
            throw new AccessDeniedHttpException('You are not authorized to display these subscriptions.');
        }

        $stream = new Stream();
        $this->sendSubscriptionsList($stream, $path, $topicFilter, $subscriberFilter, $allowedTopics, $deniedTopics);

        $headers = [
            'Content-Type' => 'application/ld+json',
        ];

        return new Response(200, $headers, $stream);
    }

    public function matchRequest(RequestInterface $request): bool
    {
        [$topic, $subscriber] = $this->extractFilters($request);
        $hasTopicPattern = false !== \strpos($topic ?? '', '{');
        $hasSubscriberPattern = false !== \strpos($subscriber ?? '', '{');
        $hasBothFilters = null !== $topic && null !== $subscriber;
        $hasNoPattern = !$hasTopicPattern && !$hasSubscriberPattern;

        $isSubscriptionIRI = $hasBothFilters && $hasNoPattern;

        return $this->matchesPattern($request) && !$isSubscriptionIRI;
    }

    private function matchesPattern(RequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return TopicMatcher::matchesTopicSelectors($path, [
            self::PATH,
            self::TOPIC_SELECTOR,
            self::TOPIC_AND_SUBSCRIBER_SELECTOR,
        ]);
    }

    private function extractFilters(RequestInterface $request): array
    {
        $path = $request->getUri()->getPath();
        $filters = \explode('/', \trim(\strtr($path, [self::PATH => '']), '/'), 2);
        $topic = nullify(\urldecode($filters[0] ?? ''));
        $subscriber = nullify(\urldecode($filters[1] ?? ''));

        return [$topic, $subscriber];
    }

    private function sendSubscriptionsList(
        Stream $stream,
        string $path,
        ?string $topic,
        ?string $subscriber,
        array $allowedTopics,
        array $deniedTopics
    ): void {
        $this->hub->hook(
            function () use ($stream, $path, $subscriber, $topic, $allowedTopics, $deniedTopics) {
                $this->hub->getLastEventID()->then(
                    function (?string $lastEventId) use (
                        $topic,
                        $subscriber,
                        $stream,
                        $path,
                        $allowedTopics,
                        $deniedTopics
                    ) {
                        $this->hub->getActiveSubscriptions($topic, $subscriber)
                            ->then(
                                function (iterable $subscriptions) use (
                                    $stream,
                                    $path,
                                    $allowedTopics,
                                    $deniedTopics,
                                    $lastEventId
                                ) {
                                    $subscriptions = $this->filterSubscriptions(
                                        $subscriptions,
                                        $allowedTopics,
                                        $deniedTopics
                                    );
                                    $result = [
                                        '@context' => 'https://mercure.rocks/',
                                        'id' => $path,
                                        'type' => 'Subscriptions',
                                        'lastEventID' => $lastEventId ?? StorageInterface::EARLIEST,
                                        'subscriptions' => $subscriptions,
                                    ];
                                    $this->sendResult($stream, $result);
                                }
                            );
                    }
                );
            }
        );
    }

    private function sendResult(Stream $stream, array $result): void
    {
        $stream->write(\json_encode($result, \JSON_THROW_ON_ERROR));
        $stream->end();
        $stream->close();
    }

    private function filterSubscriptions(iterable $subscriptions, array $allowedTopics, array $deniedTopics): array
    {
        $subscriptions = \iterable_to_array($subscriptions);
        $subscriptions = \array_filter(
            $subscriptions,
            function (Subscription $subscription) use ($allowedTopics, $deniedTopics) {
                $matchAllowedTopics = TopicMatcher::matchesTopicSelectors(
                    $subscription->getId(),
                    $allowedTopics
                );
                $matchDeniedTopics = TopicMatcher::matchesTopicSelectors(
                    $subscription->getId(),
                    $deniedTopics
                );

                return $matchAllowedTopics && !$matchDeniedTopics;
            }
        );

        return \array_values($subscriptions);
    }
}
