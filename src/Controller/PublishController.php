<?php

namespace BenTools\MercurePHP\Controller;

use BenTools\MercurePHP\Exception\Http\AccessDeniedHttpException;
use BenTools\MercurePHP\Exception\Http\BadRequestHttpException;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Model\Message;
use Lcobucci\JWT\Token;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class PublishController extends AbstractController
{
    private Authenticator $authenticator;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        $request = $this->withAttributes($request);
        $token = $request->getAttribute('token');
        $topicSelectors = $this->getAuthorizedTopicSelectors($token);
        $input = (array) $request->getParsedBody();
        $input = $this->normalizeInput($input);
        $canDispatchPrivateUpdates = ([] !== $topicSelectors);

        if ($input['private'] && !$canDispatchPrivateUpdates) {
            throw new AccessDeniedHttpException('You are not allowed to dispatch private updates.');
        }

        if (false === TopicMatcher::canUpdateTopic($input['topic'], $token, $input['private'])) {
            throw new AccessDeniedHttpException('You are not allowed to update this topic.');
        }

        $id = $input['id'] ?? (string) Uuid::uuid4();
        $message = new Message(
            $id,
            $input['data'],
            (bool) $input['private'],
            $input['type'],
            null !== $input['retry'] ? (int) $input['retry'] : null
        );

        $this->transport
            ->publish($input['topic'], $message)
            ->then(fn () => $this->storage->storeMessage($input['topic'], $message));

        $this->logger->debug(
            \sprintf(
                'Created message %s on topic %s',
                $message->getId(),
                $input['topic'],
            )
        );

        $headers = [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-cache',
        ];

        return resolve(new Response(201, $headers, $id));
    }

    public function matchRequest(RequestInterface $request): bool
    {
        return 'POST' === $request->getMethod()
            && '/.well-known/mercure' === $request->getUri()->getPath();
    }

    public function withConfig(array $config): self
    {
        /** @var self $clone */
        $clone = parent::withConfig($config);

        return $clone->withAuthenticator(Authenticator::createPublisherAuthenticator($config));
    }

    private function normalizeInput(array $input): array
    {
        if (!\is_scalar($input['topic'] ?? null)) {
            throw new BadRequestHttpException('Invalid topic parameter.');
        }

        if (!\is_scalar($input['data'] ?? '')) {
            throw new BadRequestHttpException('Invalid data parameter.');
        }

        if (isset($input['id']) && !Uuid::isValid($input['id'])) {
            throw new BadRequestHttpException('Invalid UUID.');
        }

        $input['data'] ??= null;
        $input['private'] ??= false;
        $input['type'] ??= null;
        $input['retry'] ??= null;

        return $input;
    }

    private function withAttributes(ServerRequestInterface $request): ServerRequestInterface
    {
        try {
            $token = $this->authenticator->authenticate($request);
        } catch (\RuntimeException $e) {
            throw new AccessDeniedHttpException($e->getMessage());
        }

        return $request->withAttribute('token', $token ?? null);
    }

    private function getAuthorizedTopicSelectors(?Token $token): array
    {
        if (null === $token) {
            throw new AccessDeniedHttpException('Invalid auth token.');
        }

        try {
            $claim = $token->getClaim('mercure');
        } catch (\OutOfBoundsException $e) {
            throw new AccessDeniedHttpException('Provided auth token doesn\'t contain the "mercure" claim.');
        }

        $topicSelectors = $claim->publish ?? null;

        if (null === $topicSelectors || !\is_array($topicSelectors)) {
            throw new AccessDeniedHttpException('Your are not authorized to publish on this hub.');
        }

        return $topicSelectors;
    }

    private function withAuthenticator(Authenticator $authenticator): self
    {
        $clone = clone $this;
        $clone->authenticator = $authenticator;

        return $clone;
    }
}
