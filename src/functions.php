<?php

namespace BenTools\MercurePHP;

use Lcobucci\JWT\Signer;
use Symfony\Component\Console\Input\InputInterface;
use Psr\Http\Message\UriInterface;
use Ramsey\Uuid\Uuid;

const CLIENT_NAMESPACE = '530344d8-a802-11ea-bb37-0242ac130002';

function nullify($input)
{
    if (!\is_scalar($input)) {
        return $input;
    }
    if ((string) $input === '') {
        return null;
    }

    return $input;
}

function get_signer(string $algorithm): Signer
{
    $map = [
        'HS256' => new Signer\Hmac\Sha256(),
        'RS512' => new Signer\Rsa\Sha512(),
    ];

    if (!isset($map[$algorithm])) {
        throw new \InvalidArgumentException(\sprintf('Invalid algorithm %s.', $algorithm));
    }

    return $map[$algorithm];
}

function without_nullish_values(array $array): array
{
    return \array_filter(
        $array,
        fn($value) => null !== nullify($value) && false !== $value
    );
}

function get_options_from_input(InputInterface $input): array
{
    return \array_filter(
        $input->getOptions(),
        fn($value) => null !== nullify($value) && false !== $value
    );
}

function get_client_id(string $remoteHost, int $remotePort): string
{
    return (string) Uuid::uuid5(CLIENT_NAMESPACE, \sprintf('%s:%d', $remoteHost, $remotePort));
}
