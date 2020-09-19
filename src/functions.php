<?php

namespace BenTools\MercurePHP;

use Lcobucci\JWT\Signer;

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
