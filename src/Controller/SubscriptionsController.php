<?php

namespace BenTools\MercurePHP\Controller;

use BenTools\MercurePHP\Exception\Http\AccessDeniedHttpException;
use BenTools\MercurePHP\Hub\Hub;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Security\TopicMatcher;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Stream\ThroughStream;

use function BenTools\MercurePHP\nullify;

final class SubscriptionsController extends AbstractController
{
    private const PATH = '/.well-known/mercure/subscriptions';
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
        $filters = \explode('/', \trim(\strtr($path, [self::PATH => '']), '/'), 2);
        $subscriber = nullify($filters[0]);
        $topic = nullify($filters[1] ?? null);

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

        $stream = new ThroughStream();
        $this->hub->hook(
            function () use ($stream, $path, $subscriber, $topic) {
                $this->hub->getActiveSubscriptions($subscriber, $topic)
                    ->then(
                        function (iterable $subscriptions) use ($stream, $path) {
                            $result = [
                                '@context' => 'https://mercure.rocks/',
                                'id' => $path,
                                'type' => 'Subscriptions',
                                'subscriptions' => \iterable_to_array($subscriptions),
                            ];
                            $stream->write(\json_encode($result, \JSON_THROW_ON_ERROR));
                            $stream->end();
                            $stream->close();
                        }
                    );
            }
        );

        $headers = [
            'Content-Type' => 'application/ld+json',
        ];

        return new Response(200, $headers, $stream);
    }

    public function matchRequest(RequestInterface $request): bool
    {
        return 0 === \strpos($request->getUri()->getPath(), self::PATH);
    }
}
