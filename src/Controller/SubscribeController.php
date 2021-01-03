<?php

namespace BenTools\MercurePHP\Controller;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Exception\Http\AccessDeniedHttpException;
use BenTools\MercurePHP\Exception\Http\BadRequestHttpException;
use BenTools\MercurePHP\Helpers\QueryStringParser;
use BenTools\MercurePHP\Model\Message;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Security\TopicMatcher;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Stream\ThroughStream;
use React\Stream\WritableStreamInterface as Stream;

use function BenTools\MercurePHP\nullify;
use function BenTools\QueryString\query_string;
use function React\Promise\all;
use function React\Promise\resolve;

final class SubscribeController extends AbstractController
{
    private Authenticator $authenticator;
    private LoopInterface $loop;
    private QueryStringParser $queryStringParser;

    public function __construct(
        LoopInterface $loop,
        LoggerInterface $logger
    ) {
        $this->queryStringParser = new QueryStringParser();
        $this->loop = $loop;
        $this->logger = $logger;
    }

    public function __invoke(Request $request): PromiseInterface
    {

        if ('OPTIONS' === $request->getMethod()) {
            return resolve(new Response(200));
        }

        $request = $this->withAttributes($request);

        $stream = new ThroughStream();

        $lastEventID = $request->getAttribute('lastEventId');
        $subscribedTopics = $request->getAttribute('subscribedTopics');
        $this->loop
            ->futureTick(
                fn() => $this->fetchMissedMessages($lastEventID, $subscribedTopics)
                        ->then(fn(iterable $messages) => $this->sendMissedMessages($messages, $request, $stream))
                        ->then(fn() => $this->subscribe($request, $stream))
            );

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ];

        return resolve(new Response(200, $headers, $stream));
    }

    public function matchRequest(RequestInterface $request): bool
    {
        return \in_array($request->getMethod(), ['GET', 'OPTIONS'], true)
            && '/.well-known/mercure' === $request->getUri()->getPath();
    }

    public function withConfig(array $config): self
    {
        /** @var self $clone */
        $clone = parent::withConfig($config);

        return $clone->withAuthenticator(Authenticator::createSubscriberAuthenticator($config));
    }

    private function withAttributes(Request $request): Request
    {
        try {
            $token = $this->authenticator->authenticate($request);
        } catch (\RuntimeException $e) {
            throw new AccessDeniedHttpException($e->getMessage());
        }

        $allowAnonymous = $this->config[Configuration::ALLOW_ANONYMOUS];
        if (null === $token && false === $allowAnonymous) {
            throw new AccessDeniedHttpException('Anonymous subscriptions are not allowed on this hub.', 401);
        }

        $qs = query_string($request->getUri(), $this->queryStringParser);
        $subscribedTopics = \array_map('\\urldecode', $qs->getParam('topic') ?? []);

        if ([] === $subscribedTopics) {
            throw new BadRequestHttpException('Missing "topic" parameter.');
        }

        $request = $request
            ->withQueryParams($qs->getParams())
            ->withAttribute('token', $token)
            ->withAttribute('subscribedTopics', $subscribedTopics)
            ->withAttribute('lastEventId', $this->getLastEventID($request, $qs->getParams()))
        ;

        return  $request;
    }

    private function subscribe(Request $request, Stream $stream): PromiseInterface
    {
        $allowAnonymous = $this->config[Configuration::ALLOW_ANONYMOUS];
        $subscribedTopics = $request->getAttribute('subscribedTopics');
        $token = $request->getAttribute('token');
        $promises = [];
        foreach ($subscribedTopics as $topicSelector) {
            if (!TopicMatcher::canSubscribeToTopic($topicSelector, $token, $allowAnonymous)) {
                $clientId = $request->getAttribute('clientId');
                $this->logger->debug("Client {$clientId} cannot subscribe to {$topicSelector}");
                continue;
            }
            $promises[] = $this->transport
                ->subscribe(
                    $topicSelector,
                    fn(string $topic, Message $message) => $this->sendIfAllowed($topic, $message, $request, $stream)
                )
                ->then(function (string $topic) use ($request) {
                    $clientId = $request->getAttribute('clientId');
                    $this->logger->debug("Client {$clientId} subscribed to {$topic}");
                });
        }

        if ([] === $promises) {
            return resolve(true);
        }

        return all($promises);
    }

    private function fetchMissedMessages(?string $lastEventID, array $subscribedTopics): PromiseInterface
    {
        if (null === $lastEventID) {
            return resolve([]);
        }

        return $this->storage->retrieveMessagesAfterId($lastEventID, $subscribedTopics);
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
        $allowAnonymous = $this->config[Configuration::ALLOW_ANONYMOUS];
        $subscribedTopics = $request->getAttribute('subscribedTopics');
        $token = $request->getAttribute('token');
        if (!TopicMatcher::canReceiveUpdate($topic, $message, $subscribedTopics, $token, $allowAnonymous)) {
            return resolve(false);
        }

        return resolve($this->send($topic, $message, $request, $stream));
    }

    private function send(string $topic, Message $message, Request $request, Stream $stream): PromiseInterface
    {
        $stream->write((string) $message);
        $clientId = $request->getAttribute('clientId');
        $id = $message->getId();
        $this->logger->debug("Dispatched message {$id} to client {$clientId} on topic {$topic}");

        return resolve(true);
    }

    private function getLastEventID(Request $request, array $queryParams): ?string
    {
        return nullify($request->getHeaderLine('Last-Event-ID'))
            ?? nullify($queryParams['Last-Event-ID'] ?? null)
            ?? nullify($queryParams['Last-Event-Id'] ?? null)
            ?? nullify($queryParams['last-event-id'] ?? null);
    }

    private function withAuthenticator(Authenticator $authenticator): self
    {
        $clone = clone $this;
        $clone->authenticator = $authenticator;

        return $clone;
    }
}
