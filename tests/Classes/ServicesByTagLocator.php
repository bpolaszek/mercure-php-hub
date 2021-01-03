<?php

namespace BenTools\MercurePHP\Tests\Classes;

final class ServicesByTagLocator
{
    /**
     * @var array<string|int, iterable>
     */
    private array $services;

    public function __construct(array $services = [])
    {
        foreach ($services as $tag => $service) {
            if (!\is_iterable($service)) {
                throw new \InvalidArgumentException(\sprintf('Provided services for `%s` are not iterable.', $tag));
            }
            $this->services[$tag] = $service;
        }
    }

    public function getServicesByTag(string $tag, bool $throw = true): iterable
    {
        if (!isset($this->services[$tag]) && $throw) {
            throw new \InvalidArgumentException(\sprintf('Unknown tag `%s`.', $tag));
        }

        return $this->services[$tag] ?? [];
    }
}
