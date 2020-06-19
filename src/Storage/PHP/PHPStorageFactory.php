<?php

namespace BenTools\MercurePHP\Storage\PHP;

use BenTools\MercurePHP\Storage\StorageFactoryInterface;
use React\Promise\PromiseInterface;
use RingCentral\Psr7\Uri;

use function BenTools\QueryString\query_string;
use function React\Promise\resolve;

final class PHPStorageFactory implements StorageFactoryInterface
{
    public function supports(string $dsn): bool
    {
        return 0 === \strpos($dsn, 'php://');
    }

    public function create(string $dsn): PromiseInterface
    {
        $qs = query_string(new Uri($dsn));
        $size = $qs->getParam('size') ?? 0;

        return resolve(new PHPStorage((int) $size));
    }
}
