<?php

namespace BenTools\MercurePHP\Controller;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Exception\Http\AccessDeniedHttpException;
use BenTools\MercurePHP\Exception\Http\BadRequestHttpException;
use BenTools\MercurePHP\Helpers\QueryStringParser;
use BenTools\MercurePHP\Hub\Hub;
use BenTools\MercurePHP\Model\Subscription;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Model\Message;
use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Transport\TransportInterface;
use Lcobucci\JWT\Token;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Stream\ThroughStream;
use React\Stream\WritableStreamInterface as Stream;

use function BenTools\MercurePHP\nullify;
use function BenTools\QueryString\query_string;
use function React\Promise\all;
use function React\Promise\any;
use function React\Promise\resolve;

final class SubscribeController extends AbstractController
{
    private Authenticator $authenticator;
    private LoopInterface $loop;
    private QueryStringParser $queryStringParser;
    private bool $allowAnonymous;
    /**
     * @var Hub
     */
    private Hub $hub;

    public function __construct(
        array $config,
        Hub $hub,
        StorageInterface $storage,
        TransportInterface $transport,
        Authenticator $authenticator,
        ?LoopInterface $loop = null,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->hub = $hub;
        $this->storage = $storage;
        $this->transport = $transport;
        $this->allowAnonymous = $config[Configuration::ALLOW_ANONYMOUS];
        $this->authenticator = $authenticator;
        $this->queryStringParser = new QueryStringParser();
        $this->loop = $loop ?? Factory::create();
        $this->logger = $logger;
    }

    public function __invoke(Request $request): ResponseInterface
    {

        if ('OPTIONS' === $request->getMethod()) {
            return new Response(200);
        }

        $request = $this->withAttributes($request);

        $stream = new ThroughStream();

        $lastEventID = $request->getAttribute('lastEventId');
        $subscribedTopics = $request->getAttribute('subscribedTopics');
        $this->loop
            ->futureTick(
                fn() => $this->hub->dispatchSubscriptions($request->getAttribute('subscriptions'))
                    ->then(fn() => $this->hub->fetchMissedMessages($lastEventID, $subscribedTopics))
                    ->then(fn(iterable $messages) => $this->sendMissedMessages($messages, $request, $stream))
                    ->then(fn() => $this->subscribe($request, $stream))
            );

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ];

        return new Response(200, $headers, $stream);
    }

    public function matchRequest(RequestInterface $request): bool
    {
        return \in_array($request->getMethod(), ['GET', 'OPTIONS'], true)
            && '/.well-known/mercure' === $request->getUri()->getPath();
    }

    private function withAttributes(Request $request): Request
    {
        try {
            $token = $this->authenticator->authenticate($request);
        } catch (\RuntimeException $e) {
            throw new AccessDeniedHttpException($e->getMessage());
        }

        if (null === $token && false === $this->allowAnonymous) {
            throw new AccessDeniedHttpException('Anonymous subscriptions are not allowed on this hub.', 401);
        }

        $qs = query_string($request->getUri(), $this->queryStringParser);
        $subscribedTopics = \array_map('\\urldecode', $qs->getParam('topic') ?? []);

        if ([] === $subscribedTopics) {
            throw new BadRequestHttpException('Missing "topic" parameter.');
        }

        $subscriptions = $this->createSubscriptions(
            $subscribedTopics,
            $request->getAttribute('clientId'),
            $token
        );

        $request = $request
            ->withQueryParams($qs->getParams())
            ->withAttribute('token', $token)
            ->withAttribute('subscribedTopics', $subscribedTopics)
            ->withAttribute('lastEventId', $this->getLastEventID($request, $qs->getParams()))
            ->withAttribute('subscriptions', $subscriptions ?? [])
        ;

        return $request;
    }

    private function createSubscriptions(array $subscribedTopics, string $clientId, ?Token $token): array
    {
        if (false === $this->config[Configuration::SUBSCRIPTIONS]) {
            return [];
        }

        if (null !== $token) {
            $payload = $token->getClaim('mercure')->payload ?? null;
        }

        $subscriptions = [];
        foreach ($subscribedTopics as $subscribedTopic) {
            $id = \sprintf('/.well-known/mercure/subscriptions/%s/%s', \urlencode($subscribedTopic), $clientId);
            $subscriptions[] = new Subscription(
                $id,
                $clientId,
                $subscribedTopic,
                true,
                $payload ?? null,
            );
        }

        return $subscriptions;
    }

    private function subscribe(Request $request, Stream $stream): PromiseInterface
    {
        $subscribedTopics = $request->getAttribute('subscribedTopics');
        $token = $request->getAttribute('token');
        $promises = [];
        foreach ($subscribedTopics as $topicSelector) {
            if (!TopicMatcher::canSubscribeToTopic($topicSelector, $token, $this->allowAnonymous)) {
                $clientId = $request->getAttribute('clientId');
                $this->logger()->debug("Client {$clientId} cannot subscribe to {$topicSelector}");
                continue;
            }
            $promises[] = $this->transport
                ->subscribe(
                    $topicSelector,
                    fn(string $topic, Message $message) => $this->sendIfAllowed($topic, $message, $request, $stream)
                )
                ->then(function (string $topic) use ($request) {
                    $clientId = $request->getAttribute('clientId');
                    $this->logger()->debug("Client {$clientId} subscribed to {$topic}");
                });
        }

        if ([] === $promises) {
            return resolve(true);
        }

        return all($promises);
    }

    private function sendMissedMessages(iterable $messages, Request $request, Stream $stream): PromiseInterface
    {
        $promises = [];
        foreach ($messages as $topic => $message) {
            $promises[] = $this->sendIfAllowed($topic, $message, $request, $stream);
        }

        if ([] === $promises) {
            return resolve(true);
        }

        return all($promises);
    }

    private function sendIfAllowed(string $topic, Message $message, Request $request, Stream $stream): PromiseInterface
    {
        $subscribedTopics = $request->getAttribute('subscribedTopics');
        $token = $request->getAttribute('token');
        if (!TopicMatcher::canReceiveUpdate($topic, $message, $subscribedTopics, $token, $this->allowAnonymous)) {
            return resolve(false);
        }

        return resolve($this->send($topic, $message, $request, $stream));
    }

    private function send(string $topic, Message $message, Request $request, Stream $stream): PromiseInterface
    {
        $stream->write((string) $message);
        $clientId = $request->getAttribute('clientId');
        $id = $message->getId();
        $this->logger()->debug("Dispatched message {$id} to client {$clientId} on topic {$topic}");

        return resolve(true);
    }

    private function getLastEventID(Request $request, array $queryParams): ?string
    {
        return nullify($request->getHeaderLine('Last-Event-ID'))
            ?? nullify($queryParams['Last-Event-ID'] ?? null)
            ?? nullify($queryParams['Last-Event-Id'] ?? null)
            ?? nullify($queryParams['last-event-id'] ?? null);
    }
}
