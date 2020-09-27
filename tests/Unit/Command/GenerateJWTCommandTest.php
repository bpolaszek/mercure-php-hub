<?php

namespace BenTools\MercurePHP\Tests\Unit\Command;

use BenTools\MercurePHP\Command\GenerateJWTCommand;
use BenTools\MercurePHP\Configuration\Configuration;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha512;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function BenTools\CartesianProduct\cartesian_product;
use function BenTools\MercurePHP\without_nullish_values;

$combinations = cartesian_product([
    'publish' => [
        null,
        [
            '/publish/{any}',
            '/publish/1',
        ],
    ],
    'publish_exclude' => [
        null,
        [
            '/publish/2',
        ],
    ],
    'subscribe' => [
        null,
        [
            '/subscribe/{any}',
            '/subscribe/1',
        ],
    ],
    'subscribe_exclude' => [
        null,
        [
            '/subscribe/2',
        ],
    ],
    'ttl' => [
        null,
        300,
    ]
]);

it('returns the desired JWT', function (
    ?array $publish,
    ?array $publishExclude,
    ?array $subscribe,
    ?array $subscribeExclude,
    ?int $ttl
) {

    $commandArguments = [
        '--raw' => true,
        '--jwt-key' => 'SecretKey',
        '--publish' => $publish ?? [],
        '--publish-exclude' => $publishExclude ?? [],
        '--subscribe' => $subscribe ?? [],
        '--subscribe-exclude' => $subscribeExclude ?? [],
        '--ttl' => $ttl,
    ];

    $configuration = (new Configuration())->overrideWith(without_nullish_values($_SERVER));
    $command = new GenerateJWTCommand($configuration);
        $tester = new CommandTester($command);
        $tester->execute(
            $commandArguments,
            ['interactive' => false]
        );
        $statusCode = $tester->getStatusCode();
        \assertEquals(Command::SUCCESS, $statusCode);
        $output = $tester->getDisplay();
        $token = (new Parser())->parse($output);

        \assertTrue($token->hasClaim('mercure'));

    $claim = (array) $token->getClaim('mercure');
    \assertSame(null !== $publish, \array_key_exists('publish', $claim));
    \assertSame(null !== $publishExclude, \array_key_exists('publish_exclude', $claim));
    \assertSame(null !== $subscribe, \array_key_exists('subscribe', $claim));
    \assertSame(null !== $subscribeExclude, \array_key_exists('subscribe_exclude', $claim));

    \assertSame($publish, $claim['publish'] ?? null);
    \assertSame($publishExclude, $claim['publish_exclude'] ?? null);
    \assertSame($subscribe, $claim['subscribe'] ?? null);
    \assertSame($subscribeExclude, $claim['subscribe_exclude'] ?? null);

    $later = (new \DateTimeImmutable())->modify(\sprintf('+ %d seconds', (int) $ttl + 1));
    \assertEquals(null !== $ttl, $token->isExpired($later));
})->with($combinations);

$publicKey = <<<EOF
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA0j2xXywhdpkNlHfMUxuR
2966GXjQYccP1DlxmAUyl0J+buAuNlLPVgFW/ygaF0CepKsxyv4cGYNgUnVCV+MT
G8p+ODkwNOxGMcyjPCxYdnR9ft9XfqXcTgYuJsqJ+3+NL8OA81qy3lIhZpnw5otA
EHTbTIBOyV2YoaM5BDZueaAxEpDLVH2lSRnqgSVsJOqAR5dAypw3YNwOio+GEDYG
64WP8D49fdQGBg2lIO++14jyKadoWPAvzCShLTDbvXiFSPA+bEMiP/8HPRamMicG
eRJ1nHKBNj8Ci4rAYH1vfh+Q4LTIX/yDDMctMZbhVMKYqjAbYT0x3icrqY4HuQrg
QYONKihUXsNaT6s5OZnW9RMdBEcQ9dqCGZrWORFImytcw94f++ok6QBRsWoXPbwK
DTCza3wOggPd3Rj6sqepkYxAQI5+FJWjazjf30dcAMUWgrcktdFc/xuAzzwdaZcI
zZ3CmRr4KjdrBP5wD+632kp6UMGEmns8iYA1gnKUjkhRpD2yjcunffjzmeKbiqYd
IjSPbbUFwgscKZdU3nJ+7Zue+g3GKXwvpDkNA4ZjBhd7e7L7u6cQ5rV46FnQyTMA
tmItYsnRp3OE3nAFAKIa/09C4mlUglLjFpg3ObQbGCR9LdOXfBx2U2akjbyzC3FT
Lxt0AMo1Po8CPSfbrmoXy1UCAwEAAQ==
-----END PUBLIC KEY-----
EOF;

$privateKey = <<<EOF
-----BEGIN PRIVATE KEY-----
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
nC9F0lhqsnPlKZuN1dKJzcNwr8HClg==
-----END PRIVATE KEY-----
EOF;

$combinations = cartesian_product(
    [
        'arguments' => [
            [
                '--jwt-key' => 'ASecretKey',
                '--jwt-algorithm' => 'HS256',
                '--publish' => ['/foo'],
                '--subscribe' => ['/foo'],
            ],
            [
                '--jwt-key' => $privateKey,
                '--jwt-algorithm' => 'RS512',
                '--publish' => ['/foo'],
                '--subscribe' => ['/foo'],
            ],
            [
                '--publisher-jwt-key' => 'ASecretKey',
                '--publisher-jwt-algorithm' => 'HS256',
                '--publish' => ['/foo'],
            ],
            [
                '--publisher-jwt-key' => $privateKey,
                '--publisher-jwt-algorithm' => 'RS512',
                '--publish' => ['/foo'],
            ],
            [
                '--jwt-key' => 'ASecretKey',
                '--jwt-algorithm' => 'HS256',
                '--subscriber-jwt-key' => 'ASecretKey',
                '--subscriber-jwt-algorithm' => 'HS256',
                '--subscribe' => ['/foo'],
            ],
            [
                '--jwt-key' => 'ASecretKey',
                '--jwt-algorithm' => 'HS256',
                '--subscriber-jwt-key' => $privateKey,
                '--subscriber-jwt-algorithm' => 'RS512',
                '--subscribe' => ['/foo'],
            ],
        ],
    ],
);

it(
    'signs the JWT with the appropriate key & algorithm',
    function (array $commandArguments) use ($publicKey) {

        $configuration = (new Configuration())->overrideWith(without_nullish_values($_SERVER));
        $command = new GenerateJWTCommand($configuration);
        $tester = new CommandTester($command);
        $tester->execute(
            \array_merge(['--raw' => true], $commandArguments),
            ['interactive' => false]
        );
        $statusCode = $tester->getStatusCode();
        \assertEquals(Command::SUCCESS, $statusCode);

        $output = $tester->getDisplay();

        $token = (new Parser())->parse($output);
        $algorithms = [
            $commandArguments['--jwt-algorithm'] ?? null,
            $commandArguments['--publisher-jwt-algorithm'] ?? null,
            $commandArguments['--subscriber-jwt-algorithm'] ?? null,
        ];
        if (\in_array('RS512', $algorithms, true)) {
            \assertTrue($token->verify(new Sha512(), new Key($publicKey)));
        } else {
            \assertTrue($token->verify(new Sha256(), new Key('ASecretKey')));
        }
    }
)->with($combinations);
