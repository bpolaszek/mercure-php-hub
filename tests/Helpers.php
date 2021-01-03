<?php

namespace BenTools\MercurePHP\Tests;

use BenTools\MercurePHP\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

function app(): Kernel
{
    static $kernel;

    $kernel = $kernel ?? (function () {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        $kernel = new Kernel((string) $env, (bool) $debug);
        $kernel->boot();

        return $kernel;
    })();

    return $kernel;
}

/**
 * Shortcut to the test container (all services are public).
 */
function container(): ContainerInterface
{
    $container = app()->getContainer();

    return $container->get('test.service_container');
}
