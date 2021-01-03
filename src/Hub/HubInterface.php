<?php

namespace BenTools\MercurePHP\Hub;

interface HubInterface
{
    /**
     * Accept incoming pub / sub connections (blocking)
     */
    public function run(): void;

    /**
     * Return the shutdown signal in case of interruption.
     */
    public function getShutdownSignal(): ?int;
}
