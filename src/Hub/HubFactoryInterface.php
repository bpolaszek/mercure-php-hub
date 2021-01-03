<?php

namespace BenTools\MercurePHP\Hub;

interface HubFactoryInterface
{
    public function create(): HubInterface;

    public function withConfig(array $config): self;
}
