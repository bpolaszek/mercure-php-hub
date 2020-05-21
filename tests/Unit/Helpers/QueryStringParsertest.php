<?php

namespace BenTools\MercurePHP\Tests\Unit\Helpers;

use BenTools\MercurePHP\Helpers\QueryStringParser;

it('parses the topic parameter as an array', function (string $queryString, array $expected) {
    $parser = new QueryStringParser();
    $params = $parser->parse($queryString);
    \assertArrayHasKey('topic', $params);
    \assertIsArray($params['topic']);
    \assertEquals($params['topic'], $expected);
})
->with(function () {
    yield ['topic=foo', ['foo']];
    yield ['topic=foo&topic=bar', ['foo', 'bar']];
});
