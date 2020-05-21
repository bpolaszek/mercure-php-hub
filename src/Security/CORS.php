<?php

namespace BenTools\MercurePHP\Security;

use BenTools\MercurePHP\Configuration\Configuration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CORS
{
    private array $subscriberConfig;
    private array $publisherConfig;

    public function __construct(array $config)
    {
        $this->subscriberConfig = ['origins' => self::normalizeAllowedOrigins($config[Configuration::CORS_ALLOWED_ORIGINS])];
        $this->publisherConfig = ['origins' => self::normalizeAllowedOrigins($config[Configuration::PUBLISH_ALLOWED_ORIGINS])];
        $this->subscriberConfig['all'] = self::allowsAllOrigins($this->subscriberConfig['origins']);
        $this->publisherConfig['all'] = self::allowsAllOrigins($this->publisherConfig['origins']);
    }

    public function decorateResponse(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $headers = $this->getCorsHeaders($request);
        foreach ($headers as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        return $response;
    }

    private function getCorsHeaders(ServerRequestInterface $request): array
    {
        $origin = $request->getHeaderLine('Origin');
        if (!$origin) {
            return [];
        }

        $config = 'POST' === $request->getMethod() ? $this->publisherConfig : $this->subscriberConfig;

        if (!$config['all'] && !\in_array($origin, $config['origins'], true)) {
            return [];
        }

        return [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Cache-control, Authorization, Last-Event-ID',
            'Access-Control-Max-Age' => 3600,
        ];
    }

    private static function normalizeAllowedOrigins(string $allowedOrigins): array
    {
        $allowedOrigins = \strtr($allowedOrigins, [';' => ' ', ',' => ' ']);

        return \array_map('\\trim', \explode(' ', $allowedOrigins));
    }

    private static function allowsAllOrigins(array $origins): bool
    {
        return \in_array('*', $origins, true);
    }
}
