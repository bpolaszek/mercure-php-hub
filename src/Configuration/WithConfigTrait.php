<?php

namespace BenTools\MercurePHP\Configuration;

trait WithConfigTrait
{
    protected array $config;

    public function withConfig(array $config): self
    {
        $clone = clone $this;
        $clone->config = $config;

        return $clone;
    }
}
