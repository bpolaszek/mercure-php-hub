<?php

namespace BenTools\MercurePHP\Tests\Integration\PubSub;

use Clue\React\EventSource\EventSource;
use Clue\React\EventSource\MessageEvent;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;
use Psr\Http\Message\UriInterface;
use Ramsey\Uuid\Uuid;
use React\EventLoop\Factory;
use RingCentral\Psr7\Uri;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

function createJWT(array $claims, string $key): Token
{
    $builder = new Builder();

    foreach ($claims as $name => $value) {
        $builder = $builder->withClaim($name, $value);
    }

    static $signer;
    $signer ??= new Sha256();

    return $builder->getToken($signer, new Key($key));
}

function publish(HttpClientInterface $client, UriInterface $publishUrl, Token $token, iterable $messages)
{
    foreach ($messages as $topic => $id) {
        $body = [
            'topic' => $topic,
            'data' => \sprintf("published on %s", $topic),
            'id' => $id,
        ];

        yield $client->request(
            'POST',
            $publishUrl,
            [
                'headers' => ['Authorization' => \sprintf("Bearer %s", $token)],
                'body' => $body,
                'user_data' => $id,
            ]
        );
    }
}

$url = null;
$process = null;

beforeAll(function () use (&$url, &$process) {
    $url = new Uri(sprintf("http://%s", \getenv('ADDR')));
    $transport = \getenv('TRANSPORT_URL');
    if (false === $transport) {
        throw new \RuntimeException('Cannot run test, missing TRANSPORT_URL env var.');
    }
    $process = new Process(['bin/mercure'], \dirname(__DIR__, 3), [
        'TRANSPORT_URL' => $transport,
    ]);
    $process->setTimeout(60);
    $process->setIdleTimeout(60);
    $process->start();
    \sleep(1);
});

afterAll(function () use (&$process) {
    $process->stop();
    \sleep(1);
});

it('returns a 200 status code on health check', function () use (&$url) {
    $response = HttpClient::create()->request('GET', $url->withPath('/.well-known/mercure/health'));
    \assertEquals(200, $response->getStatusCode());
});

it('returns 404 on unknown urls', function () use (&$url) {
    $response = HttpClient::create()->request('GET', $url->withPath('/foo'));
    \assertEquals(404, $response->getStatusCode());
});

it('receives updates in real time', function () use (&$url) {

    for ($uuids = [], $i = 1; $i <= 3; $i++) {
        $uuids[] = (string) Uuid::uuid4();
    }

    $token = createJWT(['mercure' => ['publish' => ['*']]], \getenv('JWT_KEY'));
    $client = HttpClient::create();
    $subscribeUrl = $url->withPath('/.well-known/mercure')->withQuery('topic=/foo&topic=/foobar/{id}');
    $publishUrl = $url->withPath('/.well-known/mercure');

    // Messages to publish
    $messages = [
        '/foo' => $uuids[0],
        '/bar' => $uuids[1],
        '/foobar/foobar' => $uuids[2],
    ];

    $expectedPublishResponse = [
        $uuids[0] => $uuids[0],
        $uuids[1] => $uuids[1],
        $uuids[2] => $uuids[2],
    ];

    $expectedReceivedEvents = [
        $uuids[0] => [
            'data' => 'published on /foo'
        ],
        $uuids[2] => [
            'data' => 'published on /foobar/foobar'
        ],
    ];

    $publishResponses = \array_map(fn() => '', $expectedPublishResponse);
    $receivedEvents = \array_map(fn() => ['data' => ''], $expectedReceivedEvents);

    $loop = Factory::create();
    $eventSource = new EventSource($subscribeUrl, $loop);
    $eventSource->on('message', function (MessageEvent $message) use (&$receivedEvents) {
            $id = $message->lastEventId;
            $data = $message->data;
            $receivedEvents[$id] = ['data' => $data];
    });

    // Once subscribed, publish some messages
    $loop->addTimer(0.1, function () use ($client, $publishUrl, $token, $messages, &$publishResponses) {
        foreach (publish($client, $publishUrl, $token, $messages) as $response) {
            $content = $response->getContent();
            $id = $response->getInfo('user_data');
            $publishResponses[$id] = $content;
        }
    });
    $loop->addTimer(4, fn() => $eventSource->close());
    $loop->run();

    foreach ($expectedPublishResponse as $id => $expectedResponse) {
        \assertEquals($expectedResponse, $publishResponses[$id]);
    }

    \assertCount(\count($receivedEvents), $expectedReceivedEvents);
    foreach ($expectedReceivedEvents as $id => $expectedEvent) {
        \assertEquals($expectedEvent, $receivedEvents[$id]);
    }
});
