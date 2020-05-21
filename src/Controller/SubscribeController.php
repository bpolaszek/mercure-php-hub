<?php

namespace BenTools\MercurePHP\Controller;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Exception\Http\AccessDeniedHttpException;
use BenTools\MercurePHP\Exception\Http\BadRequestHttpException;
use BenTools\MercurePHP\Helpers\QueryStringParser;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Transport\Message;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use React\Http;
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
    private QueryStringParser $queryStringParser;
    private bool $allowAnonymous;

    public function __construct(array $config, Authenticator $authenticator)
    {
        $this->config = $config;
        $this->allowAnonymous = $config[Configuration::ALLOW_ANONYMOUS];
        $this->authenticator = $authenticator;
        $this->queryStringParser = new QueryStringParser();
    }

    public function __invoke(Request $request, ?Stream $stream = null): ResponseInterface
    {

        $request = $this->withAttributes($request);

        if ('OPTIONS' === $request->getMethod()) {
            return new Http\Response(200);
        }

        $stream ??= new ThroughStream();

        $this->fetchMissedMessages($request->getAttribute('lastEventId'))
            ->then(fn(iterable $messages) => $this->sendMissedMessages($messages, $request, $stream))
            ->then(fn() => $this->subscribe($request, $stream));

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ];

        return new Http\Response(200, $headers, $stream);
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

    private function fetchMissedMessages(?string $lastEventID): PromiseInterface
    {
        if (null === $lastEventID) {
            return resolve([]);
        }

        return $this->storage->retrieveMessagesAfterId($lastEventID);
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
            ?? $queryParams['Last-Event-ID']
            ?? $queryParams['Last-Event-Id']
            ?? $queryParams['last-event-id']
            ?? null;
    }
}
