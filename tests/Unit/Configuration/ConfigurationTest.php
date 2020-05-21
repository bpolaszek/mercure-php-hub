<?php

namespace BenTools\MercurePHP\Tests\Unit\Configuration;

use BenTools\MercurePHP\Configuration\Configuration;

it('yells if jwt key and publisher jwt key is missing', function () {
    (new Configuration())->asArray();
})->throws(\InvalidArgumentException::class, "One of \"jwt_key\" or \"publisher_jwt_key\" configuration parameter must be defined.");

it('doesn\'t yell if jwt key is set', function () {
    $config = (new Configuration())
        ->overrideWith(['jwt_key' => 'foo'])
        ->asArray();
    \assertArrayHasKey('jwt_key', $config);
    \assertEquals('foo', $config[Configuration::JWT_KEY]);
});

it('doesn\'t yell if publisher jwt key is set', function () {
    $config = (new Configuration())
        ->overrideWith(['publisher_jwt_key' => 'foo'])
        ->asArray();
    \assertArrayHasKey('publisher_jwt_key', $config);
    \assertEquals('foo', $config[Configuration::PUBLISHER_JWT_KEY]);
});

it('handles screaming snake case', function () {
    $config = (new Configuration())
        ->overrideWith(['PUBLISHER_JWT_KEY' => 'foo'])
        ->asArray();
    \assertArrayHasKey('publisher_jwt_key', $config);
    \assertEquals('foo', $config[Configuration::PUBLISHER_JWT_KEY]);
});

it('handles kebab case as well', function () {
    $config = (new Configuration())
        ->overrideWith(['PUBLISHER-JWT-KEY' => 'foo'])
        ->asArray();
    \assertArrayHasKey('publisher_jwt_key', $config);
    \assertEquals('foo', $config[Configuration::PUBLISHER_JWT_KEY]);
});
