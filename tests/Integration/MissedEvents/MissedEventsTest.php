<?php

namespace BenTools\MercurePHP\Tests\Integration\MissedEvents;

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

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;

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

$url = new Uri(sprintf("http://%s", $_SERVER['ADDR']));
$transport = $_SERVER['TRANSPORT_URL'] ?? null;
$process = null;

beforeAll(function () use ($transport, &$process) {
    if (null === $transport) {
        throw new \RuntimeException('Cannot run test, missing TRANSPORT_URL env var.');
    }
    $process = new Process(['bin/mercure'], \dirname(__DIR__, 3));
    $process->setTimeout(15);
    $process->setIdleTimeout(15);
    $process->start();
    \sleep(1);
});

afterAll(function () use (&$process) {
    $process->stop(1, \SIGINT);
});

it('successfully receives missed events', function () use ($url) {

    $loop = Factory::create();
    for ($uuids = [], $i = 1; $i <= 3; $i++) {
        $uuids[] = (string) Uuid::uuid4();
    }

    $token = createJWT(['mercure' => ['publish' => ['*']]], $_SERVER['JWT_KEY']);
    $client = HttpClient::create();
    $subscribeUrl = $url->withPath('/.well-known/mercure')
        ->withQuery('topic=/foo&topic=/foobar/{id}&Last-Event-ID=' . $uuids[0]);
    $publishUrl = $url->withPath('/.well-known/mercure');

    // Messages to publish
    $messages = [
        '/foo' => $uuids[0],
        '/bar' => $uuids[1],
        '/foobar/foobar' => $uuids[2],
    ];

    $expectedReceivedEvents = [
        $uuids[2] => [
            'data' => 'published on /foobar/foobar'
        ],
    ];

    $receivedEvents = \array_map(fn() => ['data' => ''], $expectedReceivedEvents);

    foreach (publish($client, $publishUrl, $token, $messages) as $response) {
        $response->getContent();
    }

    $eventSource = new EventSource($subscribeUrl, $loop);

    $stop = function () use ($eventSource, $loop) {
        $eventSource->close();
        $loop->stop();
    };

    $eventSource->on(
        'message',
        function (MessageEvent $message) use (&$receivedEvents, $expectedReceivedEvents, $stop) {
            $id = $message->lastEventId;
            $data = $message->data;
            $receivedEvents[$id] = ['data' => $data];
            if (\count($receivedEvents) >= \count($expectedReceivedEvents)) {
                $stop();
            }
        }
    );

    $loop->addTimer(4, function () use ($stop) {
        $stop();
    });

    $loop->run();

    assertCount(\count($receivedEvents), $expectedReceivedEvents);
    foreach ($expectedReceivedEvents as $id => $expectedEvent) {
        assertEquals($expectedEvent, $receivedEvents[$id]);
    }
});
