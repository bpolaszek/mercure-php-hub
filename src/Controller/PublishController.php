<?php

namespace BenTools\MercurePHP\Controller;

use BenTools\MercurePHP\Exception\Http\AccessDeniedHttpException;
use BenTools\MercurePHP\Exception\Http\BadRequestHttpException;
use BenTools\MercurePHP\Hub\Hub;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Model\Message;
use Lcobucci\JWT\Token;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use React\Http\Message\Response;

final class PublishController extends AbstractController
{
    private Hub $hub;
    private Authenticator $authenticator;

    public function __construct(Hub $hub, Authenticator $authenticator)
    {
        $this->hub = $hub;
        $this->authenticator = $authenticator;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
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

        $this->hub->publish($input['topic'], $message);

        return new Response(
            201,
            [
                'Content-Type' => 'text/plain',
                'Cache-Control' => 'no-cache',
            ],
            $id
        );
    }

    public function matchRequest(RequestInterface $request): bool
    {
        return 'POST' === $request->getMethod()
            && '/.well-known/mercure' === $request->getUri()->getPath();
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
}
