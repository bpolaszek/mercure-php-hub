<?php

namespace BenTools\MercurePHP\Metrics\PHP;

use BenTools\MercurePHP\Metrics\MetricsHandlerInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class PHPMetricsHandler implements MetricsHandlerInterface
{
    private int $nbUsers = 0;

    public function resetUsers(string $localAddress): PromiseInterface
    {
        $this->nbUsers = 0;

        return resolve();
    }

    public function incrementUsers(string $localAddress): PromiseInterface
    {
        $this->nbUsers++;

        return resolve();
    }

    public function decrementUsers(string $localAddress): PromiseInterface
    {
        $this->nbUsers--;

        return resolve();
    }

    public function getNbUsers(): PromiseInterface
    {
        return resolve($this->nbUsers);
    }
}
