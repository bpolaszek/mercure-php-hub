<?php

namespace BenTools\MercurePHP\Tests\Unit\Security\TopicMatcher\Publish;

use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Tests\Classes\FilterIterator;
use BenTools\MercurePHP\Transport\Message;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;

use function BenTools\CartesianProduct\cartesian_product as combinations;

function createMercureJWT(string $key, array $mercureClaim): Token
{
    return (new Builder())
    ->withClaim('mercure', $mercureClaim)
        ->getToken(new Sha256(), new Key($key))
    ;
}

$counter = 0;
$combinations = combinations([
    'topic' => [
        '/foo/bar',
        '/foo/baz',
    ],
    'token' => [
        (new Builder())->withClaim('foo', [])->getToken(),
        createMercureJWT('secret_key', []),
        createMercureJWT('secret_key', ['publish' => null]),
        createMercureJWT('secret_key', ['publish' => []]),
        createMercureJWT('secret_key', ['publish' => ['*']]),
        createMercureJWT('secret_key', ['publish' => ['/foo/bar']]),
        createMercureJWT('secret_key', ['publish' => ['/foo/{id}']]),
        createMercureJWT('secret_key', ['publish' => ['/foo/{id}'], 'publish_exclude' => ['/foo/bar']]),
        createMercureJWT('secret_key', ['publish' => ['/foo/bar'], 'publish_exclude' => ['/foo/bar']]),
        createMercureJWT('secret_key', ['publish' => ['/foo/bar'], 'publish_exclude' => ['/foo/{id}']]),
        createMercureJWT('secret_key', ['publish' => ['/foo/bar'], 'publish_exclude' => ['*']]),
    ],
    'private' => [
        true,
        false,
    ],
    'expected' => [
        function (array $combination): bool {
            /** @var Token $token */
            $token = $combination['token'];
            $claims = $token->getClaims();
            if (!\array_key_exists('mercure', $claims)) {
                return false;
            }
            if (!isset($token->getClaim('mercure')['publish'])) {
                return false;
            }
            $included = $token->getClaim('mercure')['publish'];
            $excluded = $token->getClaim('mercure')['publish_exclude'] ?? [];
            $isIncluded = \in_array(reset($included), [$combination['topic'], '/foo/{id}', '*'], true);
            $isExcluded = \in_array(reset($excluded), [$combination['topic'], '/foo/{id}', '*'], true);
            $isNotExcluded = !$isExcluded;

            return false === $combination['private'] ? $isNotExcluded : $isIncluded && $isNotExcluded;
        },
    ],
])->asArray();

it('contains 44 combinations', function () use ($combinations) {
    \assertCount(44, $combinations);
});

test(
    'Topic can be updated only when authorized',
    function (string $topic, Token $token, bool $private, bool $expected) use (&$counter) {
        $result = TopicMatcher::canUpdateTopic($topic, $token, $private);
        \assertSame($expected, $result);
        $counter++;
    }
)->with($combinations);

test('All combinations have been tested', function () use ($combinations, &$counter) {
    \assertEquals(\count($combinations), $counter);
});
