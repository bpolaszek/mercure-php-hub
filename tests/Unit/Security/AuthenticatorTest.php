<?php

namespace BenTools\MercurePHP\Tests\Unit\Security;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Tests\Classes\FilterIterator;
use BenTools\Shh\Shh;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;
use RingCentral\Psr7\ServerRequest;

use function BenTools\CartesianProduct\cartesian_product;
use function PHPUnit\Framework\assertContains;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

function createAuthenticator(Key $key, Signer $signer): Authenticator
{
    return new Authenticator(new Parser(), $key, $signer);
}

function createJWT(Key $key, array $mercureClaim, Signer $signer): Token
{
    $builder = (new Builder())->withClaim('mercure', $mercureClaim);

    return $builder->getToken($signer, $key);
}

[$public, $private] = Shh::generateKeyPair();

it('can authenticate from an Authorization header', function (Signer $signer, Key $private, Key $public) {
        $request = new ServerRequest(
            'GET',
            '/',
            [
                'Authorization' => 'Bearer ' . createJWT($private, [], $signer),
            ]
        );
        $authenticator = createAuthenticator($public, $signer);
        $token = $authenticator->authenticate($request);
        assertTrue($token instanceof Token);
        assertTrue($token->verify($signer, $public));
})->with(function () use ($public, $private) {
    yield [new Signer\Hmac\Sha256(), new Key('foo'), new Key('foo')];
    yield [new Signer\Rsa\Sha512(), new Key($private), new Key($public)];
});

it('can authenticate from an Cookie header', function (Signer $signer, Key $private, Key $public) {
        $request = (new ServerRequest('GET', '/'))
            ->withCookieParams(['mercureAuthorization' => createJWT($private, [], $signer)]);
        $authenticator = createAuthenticator($public, $signer);
        $token = $authenticator->authenticate($request);
        assertTrue($token instanceof Token);
        assertTrue($token->verify($signer, $public));
})->with(function () use ($public, $private) {
    yield [new Signer\Hmac\Sha256(), new Key('foo'), new Key('foo')];
    yield [new Signer\Rsa\Sha512(), new Key($private), new Key($public)];
});

it('prefers Authorization header over Cookie', function (Signer $signer, Key $private, Key $public) {
        $request = (new ServerRequest('GET', '/', [
            'Authorization' => 'Bearer ' . createJWT($private, ['bar'], $signer)
            ]))
            ->withCookieParams(['mercureAuthorization' => createJWT($private, ['baz'], $signer)]);
        $authenticator = createAuthenticator($public, $signer);
        $token = $authenticator->authenticate($request);
        assertTrue($token instanceof Token);
        assertTrue($token->verify($signer, $public));
        assertContains('bar', $token->getClaim('mercure'));
})->with(function () use ($public, $private) {
    yield [new Signer\Hmac\Sha256(), new Key('foo'), new Key('foo')];
    yield [new Signer\Rsa\Sha512(), new Key($private), new Key($public)];
});

$signers = [
    'HS256' => new Signer\Hmac\Sha256(),
    'RS512' => new Signer\Rsa\Sha512(),
];

$combinations = cartesian_product(
    [
        'default_algo' => [
            null,
            'HS256',
            'RS512'
        ],
        'subscriber_algo' => [
            null,
            'HS256',
            'RS512'
        ],
        'default_key' => [
            null,
            function (array $combination) {
                $algo = $combination['subscriber_algo']
                    ?? $combination['default_algo']
                    ?? 'HS256';

                if ('HS256' === $algo) {
                    return ['default', 'default'];
                }

                [$public, $private] = Shh::generateKeyPair();

                return [$public, $private];
            }
        ],
        'subscriber_key' => [
            null,
            function (array $combination) {
                $algo = $combination['subscriber_algo']
                    ?? $combination['default_algo']
                    ?? 'HS256';

                if ('HS256' === $algo) {
                    return ['subscriber', 'subscriber'];
                }

                [$public, $private] = Shh::generateKeyPair();

                return [$public, $private];
            }
        ],
    ]
);

it('creates the subscriber authenticator', function (
    ?string $defaultAlgo,
    ?string $subscriberAlgo,
    ?array $defaultKeyPair,
    ?array $subscriberKeyPair
) use ($signers) {

    $config = [];

    if (null !== $defaultAlgo) {
        $config[Configuration::JWT_ALGORITHM] = $defaultAlgo;
    }

    if (null !== $subscriberAlgo) {
        $config[Configuration::SUBSCRIBER_JWT_ALGORITHM] = $subscriberAlgo;
    }

    if (null !== $defaultKeyPair) {
        $config[Configuration::JWT_KEY] = $defaultKeyPair[0];
    }

    if (null !== $subscriberKeyPair) {
        $config[Configuration::SUBSCRIBER_JWT_KEY] = $subscriberKeyPair[0];
    }

    $config = new Configuration($config);
    $authenticator = Authenticator::createSubscriberAuthenticator($config->asArray());
    $keyPair = $subscriberKeyPair ?? $defaultKeyPair;
    $algo = $subscriberAlgo ?? $defaultAlgo ?? 'HS256';

    $token = createJWT(new Key($keyPair[1]), [], $signers[$algo]);
    $request = (new ServerRequest('GET', '/'))
        ->withHeader('Authorization', 'Bearer ' . $token);
    assertInstanceOf(Token::class, $authenticator->authenticate($request));
})->with(
    (new FilterIterator(
        $combinations,
        fn (array $combination) => null !== $combination['default_key']
            && null !== $combination['subscriber_key']
    ))
);

$combinations = cartesian_product(
    [
        'default_algo' => [
            null,
            'HS256',
            'RS512'
        ],
        'publisher_algo' => [
            null,
            'HS256',
            'RS512'
        ],
        'default_key' => [
            null,
            function (array $combination) {
                $algo = $combination['publisher_algo']
                    ?? $combination['default_algo']
                    ?? 'HS256';

                if ('HS256' === $algo) {
                    return ['default', 'default'];
                }

                [$public, $private] = Shh::generateKeyPair();

                return [$public, $private];
            }
        ],
        'publisher_key' => [
            null,
            function (array $combination) {
                $algo = $combination['publisher_algo']
                    ?? $combination['default_algo']
                    ?? 'HS256';

                if ('HS256' === $algo) {
                    return ['publisher', 'publisher'];
                }

                [$public, $private] = Shh::generateKeyPair();

                return [$public, $private];
            }
        ],
    ]
);

it('creates the publisher authenticator', function (
    ?string $defaultAlgo,
    ?string $publisherAlgo,
    ?array $defaultKeyPair,
    ?array $publisherKeyPair
) use ($signers) {

    $config = [];

    if (null !== $defaultAlgo) {
        $config[Configuration::JWT_ALGORITHM] = $defaultAlgo;
    }

    if (null !== $publisherAlgo) {
        $config[Configuration::PUBLISHER_JWT_ALGORITHM] = $publisherAlgo;
    }

    if (null !== $defaultKeyPair) {
        $config[Configuration::JWT_KEY] = $defaultKeyPair[0];
    }

    if (null !== $publisherKeyPair) {
        $config[Configuration::PUBLISHER_JWT_KEY] = $publisherKeyPair[0];
    }

    $config = new Configuration($config);
    $authenticator = Authenticator::createPublisherAuthenticator($config->asArray());
    $keyPair = $publisherKeyPair ?? $defaultKeyPair;
    $algo = $publisherAlgo ?? $defaultAlgo ?? 'HS256';

    $token = createJWT(new Key($keyPair[1]), [], $signers[$algo]);
    $request = (new ServerRequest('GET', '/'))
        ->withHeader('Authorization', 'Bearer ' . $token);
    assertInstanceOf(Token::class, $authenticator->authenticate($request));
})->with(
    (new FilterIterator(
        $combinations,
        fn (array $combination) => null !== $combination['default_key']
            && null !== $combination['publisher_key']
    ))
);
