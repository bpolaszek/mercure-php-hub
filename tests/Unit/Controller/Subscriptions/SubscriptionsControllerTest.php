<?php

namespace BenTools\MercurePHP\Tests\Unit\Controller\Subscriptions;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Controller\SubscriptionsController;
use BenTools\MercurePHP\Exception\Http\AccessDeniedHttpException;
use BenTools\MercurePHP\Hub\HubFactory;
use BenTools\MercurePHP\Security\Authenticator;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use RingCentral\Psr7\ServerRequest;

use function BenTools\MercurePHP\get_client_id;

function createSubscriptionsRequest(?string $subscriber = null, ?string $topic = null): ServerRequestInterface
{
    $uri = '/.well-known/mercure/subscriptions';
    if (null !== $subscriber) {
        $uri .= "/{$subscriber}";
    }
    if (null !== $topic) {
        $uri .= null === $subscriber ? "/{subscriber}/{$topic}" : "/{$topic}";
    }

    return (new ServerRequest('GET', $uri))
        ->withAttribute('clientId', get_client_id('127.0.0.1', 12345));
}

function createJWT(string $key, array $subscribedTopics = [], array $subscribedDeniedTopics = []): Token
{
    $builder = (new Builder())->withClaim('mercure', [
        'subscribe' => $subscribedTopics,
        'subscribe_exclude' => $subscribedDeniedTopics,
    ]);

    static $signer;
    $signer ??= new Sha256();

    return $builder->getToken($signer, new Key($key));
}

function authenticate(ServerRequestInterface $request, Token $token): ServerRequestInterface
{
    return $request->withHeader('Authorization', 'Bearer ' . $token);
}

it('returns a 403 when user is not logged in', function () {
    $config = new Configuration(['jwt_key' => 'foo', 'subscriptions' => true]);
    $loop = Factory::create();
    $factory = new HubFactory($config->asArray(), $loop);
    $hub = $factory->create();
    $authenticator = Authenticator::createSubscriberAuthenticator($config->asArray());
    $handle = new SubscriptionsController($hub, $authenticator);
    $handle(createSubscriptionsRequest());
})->throws(
    AccessDeniedHttpException::class,
    'You must be authenticated to access the Subscription API.'
);

it('returns a 403 when user hasn\'t sufficient privileges', function () {
    $config = new Configuration(['jwt_key' => 'foo', 'subscriptions' => true]);
    $loop = Factory::create();
    $factory = new HubFactory($config->asArray(), $loop);
    $hub = $factory->create();
    $jwt = createJWT('foo');
    $authenticator = Authenticator::createSubscriberAuthenticator($config->asArray());
    $handle = new SubscriptionsController($hub, $authenticator);
    $handle(authenticate(createSubscriptionsRequest(), $jwt));
})->throws(
    AccessDeniedHttpException::class,
    'You are not authorized to display these subscriptions.'
);

it('returns subscriptions', function () {
    $config = new Configuration(['jwt_key' => 'foo', 'subscriptions' => true]);
    $loop = Factory::create();
    $factory = new HubFactory($config->asArray(), $loop);
    $hub = $factory->create();
    $jwt = createJWT('foo', [
        '/.well-known/mercure/subscriptions'
    ]);
    $response = $hub->handle(authenticate(createSubscriptionsRequest(), $jwt));
    \assertEquals(200, $response->getStatusCode());
});
