<?php

namespace BenTools\MercurePHP\Configuration;

use Symfony\Component\Console\Input\InputInterface;

use function BenTools\MercurePHP\nullify;

final class Configuration
{
    public const ADDR = 'addr';
    public const TRANSPORT_URL = 'transport_url';
    public const STORAGE_URL = 'storage_url';
    public const METRICS_URL = 'metrics_url';
    public const CORS_ALLOWED_ORIGINS = 'cors_allowed_origins';
    public const PUBLISH_ALLOWED_ORIGINS = 'publish_allowed_origins';
    public const JWT_KEY = 'jwt_key';
    public const JWT_ALGORITHM = 'jwt_algorithm';
    public const PUBLISHER_JWT_KEY = 'publisher_jwt_key';
    public const PUBLISHER_JWT_ALGORITHM = 'publisher_jwt_algorithm';
    public const SUBSCRIBER_JWT_KEY = 'subscriber_jwt_key';
    public const SUBSCRIBER_JWT_ALGORITHM = 'subscriber_jwt_algorithm';
    public const ALLOW_ANONYMOUS = 'allow_anonymous';

    public const DEFAULT_ADDR = '127.0.0.1:3000';
    public const DEFAULT_TRANSPORT_URL = 'php://localhost?size=1000';
    public const DEFAULT_JWT_ALGORITHM = 'HS256';
    public const DEFAULT_CORS_ALLOWED_ORIGINS = '*';
    public const DEFAULT_PUBLISH_ALLOWED_ORIGINS = '*';

    private const DEFAULT_CONFIG = [
        self::ADDR => self::DEFAULT_ADDR,
        self::TRANSPORT_URL => self::DEFAULT_TRANSPORT_URL,
        self::STORAGE_URL => null,
        self::METRICS_URL => null,
        self::CORS_ALLOWED_ORIGINS => self::DEFAULT_CORS_ALLOWED_ORIGINS,
        self::PUBLISH_ALLOWED_ORIGINS => self::DEFAULT_PUBLISH_ALLOWED_ORIGINS,
        self::JWT_KEY => null,
        self::JWT_ALGORITHM => self::DEFAULT_JWT_ALGORITHM,
        self::PUBLISHER_JWT_KEY => null,
        self::PUBLISHER_JWT_ALGORITHM => null,
        self::SUBSCRIBER_JWT_KEY => null,
        self::SUBSCRIBER_JWT_ALGORITHM => null,
        self::ALLOW_ANONYMOUS => false,
    ];

    private array $config = self::DEFAULT_CONFIG;

    public function __construct(array $config = [])
    {
        foreach ($config as $key => $value) {
            $this->set($key, $value);
        }
    }

    private function export(): array
    {
        $config = \array_map(fn($value) => \is_string($value) && '' === \trim($value) ? null : $value, $this->config);
        if (null === $config[self::JWT_KEY] && null === $config[self::PUBLISHER_JWT_KEY]) {
            throw new \InvalidArgumentException(
                "One of \"jwt_key\" or \"publisher_jwt_key\" configuration parameter must be defined."
            );
        }

        return $config;
    }

    public function asArray(): array
    {
        return $this->export();
    }

    private function set(string $key, $value): void
    {
        $key = self::normalize($key);
        if (!\array_key_exists($key, self::DEFAULT_CONFIG)) {
            return;
        }
        if (null === $value && \is_bool(self::DEFAULT_CONFIG[$key])) {
            $value = self::DEFAULT_CONFIG[$key];
        }
        $this->config[$key] = $value;
    }

    public function overrideWith(array $values): self
    {
        $clone = clone $this;
        foreach ($values as $key => $value) {
            $clone->set($key, $value);
        }

        return $clone;
    }

    private static function normalize(string $key): string
    {
        return \strtolower(\strtr($key, ['-' => '_']));
    }

    public static function bootstrapFromCLI(InputInterface $input): self
    {
        return (new self())
            ->overrideWith($_SERVER)
            ->overrideWith(self::filterCLIInput($input));
    }

    private static function filterCLIInput(InputInterface $input): array
    {
        return \array_filter(
            $input->getOptions(),
            fn($value) => null !== nullify($value) && false !== $value
        );
    }
}
