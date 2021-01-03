<?php

namespace BenTools\MercurePHP\Security;

use BenTools\MercurePHP\Configuration\Configuration;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function BenTools\MercurePHP\get_signer;

final class Authenticator
{
    private Parser $parser;
    private Key $key;
    private Signer $signer;

    public function __construct(Parser $parser, Key $key, Signer $signer)
    {
        $this->parser = $parser;
        $this->key = $key;
        $this->signer = $signer;
    }

    public function authenticate(ServerRequestInterface $request): ?Token
    {
        $token = self::extractToken($request, $this->parser, $this->key, $this->signer);

        if (null === $token) {
            return null;
        }

        if (!$token->verify($this->signer, $this->key)) {
            throw new RuntimeException('Invalid token signature.');
        }

        if ($token->isExpired()) {
            throw new RuntimeException('Your token has expired.');
        }

        return $token;
    }

    private static function extractRawToken(ServerRequestInterface $request): ?string
    {
        if ($request->hasHeader('Authorization')) {
            $payload = \trim($request->getHeaderLine('Authorization'));
            if (0 === \strpos($payload, 'Bearer ')) {
                return \substr($payload, 7);
            }
        }

        $cookies = $request->getCookieParams();
        return $cookies['mercureAuthorization'] ?? null;
    }

    private static function extractToken(ServerRequestInterface $request, Parser $parser, Key $key, Signer $signer): ?Token
    {
        $payload = self::extractRawToken($request);
        if (null === $payload) {
            return null;
        }

        try {
            return $parser->parse($payload);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Cannot decode token.");
        }
    }

    public static function createPublisherAuthenticator(array $config): Authenticator
    {
        $publisherKey = $config[Configuration::PUBLISHER_JWT_KEY] ?? $config[Configuration::JWT_KEY];
        $publisherAlgorithm = $config[Configuration::PUBLISHER_JWT_ALGORITHM] ?? $config[Configuration::JWT_ALGORITHM];

        return new self(
            new Parser(),
            new Key($publisherKey),
            get_signer($publisherAlgorithm)
        );
    }

    public static function createSubscriberAuthenticator(array $config): Authenticator
    {
        $subscriberKey = $config[Configuration::SUBSCRIBER_JWT_KEY] ?? $config[Configuration::JWT_KEY];
        $subscriberAlgorithm = $config[Configuration::SUBSCRIBER_JWT_ALGORITHM] ?? $config[Configuration::JWT_ALGORITHM];

        return new self(
            new Parser(),
            new Key($subscriberKey),
            get_signer($subscriberAlgorithm)
        );
    }
}
