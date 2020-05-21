<?php

namespace BenTools\MercurePHP\Tests\Unit\Security;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Tests\Classes\FilterIterator;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;
use RingCentral\Psr7\ServerRequest;

use function BenTools\CartesianProduct\cartesian_product;

function sign()
{
    static $signer;
    $signer ??= new Sha256();

    return $signer;
}

function createAuthenticator(string $key): Authenticator
{
    return new Authenticator(new Parser(), new Key($key), sign());
}

function createJWT(string $key, array $mercureClaim = []): Token
{
    $builder = (new Builder())->withClaim('mercure', $mercureClaim);

    return $builder->getToken(sign(), new Key($key));
}

it(
    'can authenticate from an Authorization header',
    function () {
        $request = new ServerRequest(
            'GET',
            '/',
            [
                'Authorization' => 'Bearer ' . createJWT('foo'),
            ]
        );
        $authenticator = createAuthenticator('foo');
        $token = $authenticator->authenticate($request);
        \assertTrue($token instanceof Token);
        \assertTrue($token->verify(sign(), new Key('foo')));
    }
);

it(
    'can authenticate from an Cookie header',
    function () {
        $request = (new ServerRequest('GET', '/'))
            ->withCookieParams(['mercureAuthorization' => createJWT('foo')]);
        $authenticator = createAuthenticator('foo');
        $token = $authenticator->authenticate($request);
        \assertTrue($token instanceof Token);
        \assertTrue($token->verify(sign(), new Key('foo')));
    }
);

it(
    'prefers Authorization header over Cookie',
    function () {
        $request = (new ServerRequest(
            'GET',
            '/',
            [
                'Authorization' => 'Bearer ' . createJWT('foo', ['bar']),
            ]
        ))
            ->withCookieParams(['mercureAuthorization' => createJWT('foo', ['baz'])]);
        $authenticator = createAuthenticator('foo');
        $token = $authenticator->authenticate($request);
        \assertTrue($token instanceof Token);
        \assertTrue($token->verify(sign(), new Key('foo')));
        \assertContains('bar', $token->getClaim('mercure'));
    }
);

# Factories
$combinations = cartesian_product(
    [
        Configuration::JWT_KEY => [
            null,
            'default_jwt_key',
        ],
        Configuration::SUBSCRIBER_JWT_KEY => [
            null,
            'subscriber_jwt_key',
        ],
        Configuration::PUBLISHER_JWT_KEY => [
            null,
            'publisher_jwt_key',
        ],
    ]
);

it(
    'creates the subscriber authenticator',
    function (?string $defaultKey, ?string $subscriberKey, ?string $publisherKey) {
        $config = new Configuration(
            [
                Configuration::JWT_KEY => $defaultKey,
                Configuration::SUBSCRIBER_JWT_KEY => $subscriberKey,
                Configuration::PUBLISHER_JWT_KEY => $publisherKey,
            ]
        );
        $authenticator = Authenticator::createSubscriberAuthenticator($config->asArray());
        $token = createJWT($subscriberKey ?? $defaultKey);
        $request = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Bearer ' . $token);
        \assertInstanceOf(Token::class, $authenticator->authenticate($request));
    }
)->with(
    (new FilterIterator(
        $combinations,
        fn (array $combination) => null !== $combination[Configuration::JWT_KEY]
            && null !== $combination[Configuration::SUBSCRIBER_JWT_KEY]
    ))
);

it(
    'creates the publisher authenticator',
    function (?string $defaultKey, ?string $subscriberKey, ?string $publisherKey) {
        $config = new Configuration(
            [
                Configuration::JWT_KEY => $defaultKey,
                Configuration::SUBSCRIBER_JWT_KEY => $subscriberKey,
                Configuration::PUBLISHER_JWT_KEY => $publisherKey,
            ]
        );
        $authenticator = Authenticator::createPublisherAuthenticator($config->asArray());
        $token = createJWT($publisherKey ?? $defaultKey);
        $request = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Bearer ' . $token);
        \assertInstanceOf(Token::class, $authenticator->authenticate($request));
    }
)->with(
    (new FilterIterator(
        $combinations,
        fn (array $combination) => null !== $combination[Configuration::JWT_KEY]
            && null !== $combination[Configuration::PUBLISHER_JWT_KEY]
    ))
);
