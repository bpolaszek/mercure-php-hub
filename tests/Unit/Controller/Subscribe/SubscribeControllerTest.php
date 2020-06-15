<?php

namespace BenTools\MercurePHP\Tests\Unit\Controller\Subscribe;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Controller\SubscribeController;
use BenTools\MercurePHP\Exception\Http\AccessDeniedHttpException;
use BenTools\MercurePHP\Exception\Http\BadRequestHttpException;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Storage\NullStorage\NullStorage;
use BenTools\MercurePHP\Tests\Classes\NullTransport;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use RingCentral\Psr7\ServerRequest;
use RingCentral\Psr7\Uri;

function createController(Configuration $configuration, ?Authenticator $authenticator = null)
{
    $config = $configuration->asArray();
    $authenticator ??= new Authenticator(new Parser(), new Key($config['jwt_key']), new Sha256());

    return (new SubscribeController($config, $authenticator))
        ->withTransport(new NullTransport())
        ->withStorage(new NullStorage())
        ->withLoop(Factory::create())
        ;
}

function createJWT(array $claims, string $key, ?int $expires = null): Token
{
    $expires ??= (new \DateTime('tomorrow'))->format('U');
    $builder = (new Builder())->expiresAt($expires);

    foreach ($claims as $name => $value) {
        $builder = $builder->withClaim($name, $value);
    }

    static $signer;
    $signer ??= new Sha256();

    return $builder->getToken($signer, new Key($key));
}

function authenticate(ServerRequestInterface $request, Token $token): ServerRequestInterface
{
    return $request->withHeader('Authorization', 'Bearer ' . $token);
}

function createSubscribeRequest(array $subscribedTopics = ['/lobby']): ServerRequestInterface
{
    $uri = new Uri('/.well-known/mercure');
    $uri = $uri->withQuery(
        implode('&', \array_map(fn (string $topic) => 'topic=' . $topic, $subscribedTopics))
    );
    return (new ServerRequest('GET', $uri));
}

it('will respond to the Mercure publish url', function () {
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = new ServerRequest('GET', '/.well-known/mercure');
    \assertTrue($handle->matchRequest($request));
});

it('will not respond when requet method is not get', function () {
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = new ServerRequest('POST', '/.well-known/mercure');
    \assertFalse($handle->matchRequest($request));
});

# Authentication / Authorization
it('yells when anonymous subscriptions are not allowed and no auth is provided', function () {
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = createSubscribeRequest();
    $handle($request);
})->throws(
    AccessDeniedHttpException::class,
    'Anonymous subscriptions are not allowed on this hub.'
);

it('doesn\'t yell when anonymous subscriptions are allowed', function () {
    $handle = createController(new Configuration(['jwt_key' => 'foo', 'allow_anonymous' => true]));
    $request = createSubscribeRequest();
    $handle($request);
    \assertTrue(true);
});

it('yells if token is not signed', function () {
    $token = createJWT(['foo' => 'bar'], 'foo');
    $handle = createController(new Configuration(['jwt_key' => 'bar']));
    $request = authenticate(createSubscribeRequest(), $token);
    $handle($request);
})->throws(AccessDeniedHttpException::class, 'Invalid token signature.');

it('yells if token is expired', function () {
    $token = createJWT(['foo' => 'bar'], 'foo', (new \DateTime('yesterday'))->format('U'));
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = authenticate(createSubscribeRequest(), $token);
    $handle($request);
})->throws(AccessDeniedHttpException::class, 'Your token has expired.');

it('creates an event stream response', function () {
    $handle = createController(new Configuration(['jwt_key' => 'foo', 'allow_anonymous' => true]));
    $request = createSubscribeRequest();
    $response = $handle($request);
    \assertEquals('text/event-stream', $response->getHeaderLine('Content-Type'));
});

it('throws a 400 Bad request if no topic is provided', function () {
    $handle = createController(new Configuration(['jwt_key' => 'foo', 'allow_anonymous' => true]));
    $request = createSubscribeRequest([]);
    $response = $handle($request);
})->throws(BadRequestHttpException::class, 'Missing "topic" parameter.');
