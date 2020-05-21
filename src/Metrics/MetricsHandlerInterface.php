<?php

namespace BenTools\MercurePHP\Metrics;

use React\Promise\PromiseInterface;

interface MetricsHandlerInterface
{
    public function resetUsers(string $localAddress): PromiseInterface;
    public function incrementUsers(string $localAddress): PromiseInterface;
    public function decrementUsers(string $localAddress): PromiseInterface;
    public function getNbUsers(): PromiseInterface;
}
