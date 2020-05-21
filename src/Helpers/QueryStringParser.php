<?php

namespace BenTools\MercurePHP\Helpers;

use BenTools\QueryString\Parser\QueryStringParserInterface;

use function BenTools\QueryString\pairs;

final class QueryStringParser implements QueryStringParserInterface
{
    private const FORCE_PARAM_AS_ARRAY = [
        'topic',
    ];

    public function parse(string $queryString): array
    {
        $params = [];

        foreach (pairs($queryString) as $key => $value) {
            if (isset($params[$key]) || \in_array($key, self::FORCE_PARAM_AS_ARRAY, true)) {
                $params[$key] = (array) ($params[$key] ?? null);
                $params[$key][] = $value;
            } else {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}
