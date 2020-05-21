<?php

namespace BenTools\MercurePHP\Helpers;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\NullLogger;

trait LoggerAwareTrait
{
    /**
     * @internal
     * @deprecated - Please call $this->logger() instead.
     */
    protected ?LoggerInterface $logger;

    /**
     * @deprecated - Use withLogger instead.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function withLogger(LoggerInterface $logger): self
    {
        $clone = clone $this;
        $clone->logger = $logger;

        return $clone;
    }

    public function logger(): LoggerInterface
    {
        static $nullLogger;

        return $this->logger ?? ($nullLogger ??= new NullLogger());
    }
}
