<?php

namespace BenTools\MercurePHP\Tests\Unit\Security;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Security\CORS;
use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;

use function BenTools\CartesianProduct\cartesian_product;

function createRequest(string $method, string $origin = null): ServerRequestInterface
{
    $request = new ServerRequest($method, '/');
    if (null !== $origin) {
        $request = $request->withHeader('Origin', $origin);
    }

    return $request;
}

function createResponse(): Response
{
    return new Response(200);
}

$combinations = cartesian_product(
    [
        'method' => [
            'GET',
            'POST',
        ],
        'origin' => [
            'http://www.example.com',
            'https://good.example.com',
            'http://good.example.com',
            'http://bad.example.com',
            null,
        ],
        'cors_allowed_origins' => [
            '*',
            'http://example.com',
            'http://www.example.com',
            'http://www.example.com,https://good.example.com',
            'http://www.example.com;https://good.example.com',
            'http://www.example.com https://good.example.com',
        ],
        'publish_allowed_origins' => [
            '*',
            'http://example.com',
            'http://www.example.com',
            'http://www.example.com,https://good.example.com',
            'http://www.example.com;https://good.example.com',
            'http://www.example.com https://good.example.com',
        ],
        'expected' => [
            static function (array $combination) {
                $config = 'POST' === $combination['method'] ? $combination['publish_allowed_origins'] : $combination['cors_allowed_origins'];
                $matchAll = false !== \strpos($config, '*');

                // No origin provided -> no header
                if (null === $combination['origin']) {
                    return null;
                }

                // All origins allowed -> return provided origin
                if ($matchAll) {
                    return $combination['origin'];
                }

                // Otherwise, check if origin is explicitely listed
                return false !== \strpos($config, $combination['origin']) ? $combination['origin'] : '';
            },
        ],
    ]
);

it(
    'returns the origin, and only when origin matches',
    function (
        string $method,
        ?string $origin,
        string $allowedOrigins,
        ?string $publishAllowedOrigins,
        ?string $expected
    ) {
        $config = new Configuration(
            [
                Configuration::JWT_KEY => 'foo',
                Configuration::CORS_ALLOWED_ORIGINS => $allowedOrigins,
                Configuration::PUBLISH_ALLOWED_ORIGINS => $publishAllowedOrigins,
            ]
        );
        $request = createRequest($method, $origin);
        $cors = new CORS($config->asArray());
        $response = $cors->decorateResponse($request, createResponse());
        \assertEquals($expected, $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
)->with($combinations);
