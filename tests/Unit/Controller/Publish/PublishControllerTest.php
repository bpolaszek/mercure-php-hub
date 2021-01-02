<?php

namespace BenTools\MercurePHP\Tests\Unit\Controller\Publish;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Controller\PublishController;
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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use RingCentral\Psr7\ServerRequest;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

function createController(Configuration $configuration, ?Authenticator $authenticator = null)
{
    $config = $configuration->asArray();
    $authenticator ??= new Authenticator(new Parser(), new Key($config['jwt_key']), new Sha256());

    return (new PublishController($authenticator))
        ->withStorage(new NullStorage())
        ->withTransport(new NullTransport())
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

function createPublishRequest(?array $postData = []): ServerRequestInterface
{
    return (new ServerRequest('POST', '/.well-known/mercure', []))->withParsedBody($postData);
}

it('will respond to the Mercure publish url', function () {
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = new ServerRequest('POST', '/.well-known/mercure');
    assertTrue($handle->matchRequest($request));
});

it('will not respond when requet method is not post', function () {
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = new ServerRequest('GET', '/.well-known/mercure');
    assertFalse($handle->matchRequest($request));
});

# Authentication / Authorization
it('yells when no authorization header is present', function () {
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = new ServerRequest('POST', '/.well-known/mercure');
    $handle($request);
})->throws(AccessDeniedHttpException::class, 'Invalid auth token.');

it('yells if token is not signed', function () {
    $token = createJWT(['foo' => 'bar'], 'foo');
    $transport = new NullTransport();
    $storage = new NullStorage();
    $handle = createController(new Configuration(['jwt_key' => 'bar']));
    $request = authenticate(createPublishRequest(), $token);
    $handle($request);
})->throws(AccessDeniedHttpException::class, 'Invalid token signature.');

it('yells if token is expired', function () {
    $token = createJWT(['foo' => 'bar'], 'foo', (new \DateTime('yesterday'))->format('U'));
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = authenticate(createPublishRequest(), $token);
    $handle($request);
})->throws(AccessDeniedHttpException::class, 'Your token has expired.');

it('yells if token has no mercure claim', function () {
    $token = createJWT(['foo' => 'bar'], 'foo');
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = authenticate(createPublishRequest(), $token);
    $handle($request);
})->throws(
    AccessDeniedHttpException::class,
    'Provided auth token doesn\'t contain the "mercure" claim.'
);

it('yells if publishing is not authorized', function (array $claims) {
    $token = createJWT($claims, 'foo');
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = authenticate(createPublishRequest(), $token);
    $handle($request);
})
    ->throws(
        AccessDeniedHttpException::class,
        'Your are not authorized to publish on this hub.'
    )
    ->with(function () {
        yield [['mercure' => ['subscribe' => ['*']]]];
        yield [['mercure' => ['publish' => null]]];
        yield [['mercure' => ['publish' => '*']]];
    });

# Input validation
it('yells if topic is invalid', function (?array $postData = null) {
    $token = createJWT(['mercure' => ['publish' => []]], 'foo');
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = authenticate(createPublishRequest($postData), $token);
    $handle($request);
})
    ->throws(
        BadRequestHttpException::class,
        'Invalid topic parameter.'
    )
    ->with(function () {
        yield [];
        yield [['data' => 'foo']];
        yield [['topic' => ['foo']]];
        yield [['topic' => null]];
    });

it('yells if data is invalid', function (?array $postData = null) {
    $token = createJWT(['mercure' => ['publish' => []]], 'foo');
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = authenticate(createPublishRequest($postData), $token);
    $handle($request);
})
->throws(
    BadRequestHttpException::class,
    'Invalid data parameter.'
)
->with(function () {
    yield [['topic' => '/foo', 'data' => []]];
    yield [['topic' => '/foo', 'data' => new \stdClass()]];
});

it('yells when trying to dispatch an unauthorized private update', function (?array $postData = null) {
    $token = createJWT(['mercure' => ['publish' => []]], 'foo');
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = authenticate(createPublishRequest($postData), $token);
    $handle($request);
})
->throws(
    AccessDeniedHttpException::class,
    'You are not allowed to dispatch private updates.'
)
->with(function () {
    yield [['topic' => '/foo', 'data' => 'foo', 'private' => true]];
});

it('yells when trying to dispatch an unauthorized update', function (?array $postData = null) {
    $token = createJWT(['mercure' => ['publish' => ['/foo/bar'], 'publish_exclude' => ['/foo/{id}']]], 'foo');
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = authenticate(createPublishRequest($postData), $token);
    $handle($request);
})
->throws(
    AccessDeniedHttpException::class,
    'You are not allowed to update this topic.'
)
->with(function () {
    yield [['topic' => '/foo/bar', 'data' => 'foo']];
});

it('publishes an update to the hub', function () {
    $token = createJWT(['mercure' => ['publish' => []]], 'foo');
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $request = authenticate(createPublishRequest(['topic' => '/foo', 'data' => 'bar']), $token);
    $response = $handle($request);
    assertInstanceOf(ResponseInterface::class, $response);
    assertEquals(201, $response->getStatusCode());
    assertTrue(Uuid::isValid((string) $response->getBody()));
});

it('accepts an UUID from client', function () {
    $token = createJWT(['mercure' => ['publish' => []]], 'foo');
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $id = (string) Uuid::uuid4();
    $request = authenticate(createPublishRequest(['topic' => '/foo', 'data' => 'bar', 'id' => $id]), $token);
    $response = $handle($request);
    $content = (string) $response->getBody();
    assertInstanceOf(ResponseInterface::class, $response);
    assertEquals(201, $response->getStatusCode());
    assertTrue(Uuid::isValid($content));
    assertEquals($id, $content);
});

it('yells if client sends an invalid UUID', function () {
    $token = createJWT(['mercure' => ['publish' => []]], 'foo');
    $handle = createController(new Configuration(['jwt_key' => 'foo']));
    $id = 'foobar';
    $request = authenticate(createPublishRequest(['topic' => '/foo', 'data' => 'bar', 'id' => $id]), $token);
    $handle($request);
})->throws(BadRequestHttpException::class, 'Invalid UUID.');
