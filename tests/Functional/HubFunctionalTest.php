<?php

namespace BenTools\MercurePHP\Tests\Functional;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;
use RingCentral\Psr7\Uri;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

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

$url = null;

beforeAll(
    function () use (&$url) {
        $url = new Uri(sprintf("http://%s", \getenv('ADDR')));
        $process = new Process(['bin/mercure'], \dirname(__DIR__, 2));
        $process->setTimeout(10);
        $process->setIdleTimeout(10);
        $process->start();
        \usleep(500000);
    }
);

it(
    'returns a 200 status code on health check',
    function () use (&$url) {
        \assertEquals(
            200,
            HttpClient::create()->request('GET', $url->withPath('/.well-known/mercure/health'))->getStatusCode()
        );
    }
);

it(
    'returns 404 on unknown urls',
    function () use (&$url) {
        \assertEquals(
            404,
            HttpClient::create()->request('GET', $url->withPath('/foo'))->getStatusCode()
        );
    }
);

test(
    'publish and subscribe work as expected',
    function () use (&$url) {
        $client = HttpClient::create();
        \assertTrue(true);

        $requests = [];

        $subscribeUrl = $url->withPath('/.well-known/mercure')->withQuery('topic=/foo&topic=/foobar/{id}');
        $publishUrl = $url->withPath('/.well-known/mercure');

        // Subscribe
        $requests[] = $client->request(
            'GET',
            $subscribeUrl,
            [
                'user_data' => 'subscribe_request',
            ]
        );
        usleep(50000);

        // Publish some messages
        $token = createJWT(['mercure' => ['publish' => ['*']]], \getenv('JWT_KEY'));
        $messages = [
            '/foo' => '849afa67-92b4-433f-b0cd-fc639ed76968',
            '/bar' => 'c9d3f2eb-fd8d-469a-b530-9fcce9ee8363',
            '/foobar/foobar' => '416776e5-cc89-4632-adac-793e694f4c6f',
        ];

        foreach ($messages as $topic => $id) {
            $body = [
                'topic' => $topic,
                'data' => \sprintf("published on %s", $topic),
                'id' => $id,
            ];

            $requests[] = $client->request('POST', $publishUrl, [
                    'headers' => ['Authorization' => \sprintf("Bearer %s", $token)],
                    'body' => $body,
                    'user_data' => $id,
                ]);
        }

        $expectedResponses = [
            '849afa67-92b4-433f-b0cd-fc639ed76968' => '849afa67-92b4-433f-b0cd-fc639ed76968',
            'c9d3f2eb-fd8d-469a-b530-9fcce9ee8363' => 'c9d3f2eb-fd8d-469a-b530-9fcce9ee8363',
            '416776e5-cc89-4632-adac-793e694f4c6f' => '416776e5-cc89-4632-adac-793e694f4c6f',
            'subscribe_request' => <<<EOF
id:849afa67-92b4-433f-b0cd-fc639ed76968
data:published on /foo

id:416776e5-cc89-4632-adac-793e694f4c6f
data:published on /foobar/foobar


EOF,
        ];

        $responses = \array_map(fn () => '', $expectedResponses);

        try {
            foreach ($client->stream($requests, 2) as $response => $chunk) {
                $id = $response->getInfo('user_data');
                $responses[$id] .= $chunk->getContent();
            }
        } catch (TimeoutException $e) {
        }

        foreach ($expectedResponses as $id => $expectedResponse) {
            \assertEquals($expectedResponse, $responses[$id]);
        }
    }
);
