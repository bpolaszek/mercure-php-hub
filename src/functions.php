<?php

namespace BenTools\MercurePHP;

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
