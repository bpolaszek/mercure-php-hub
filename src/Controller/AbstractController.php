<?php

namespace BenTools\MercurePHP\Controller;

use BenTools\MercurePHP\Helpers\LoggerAwareTrait;
use BenTools\Psr7\RequestMatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractController implements RequestMatcherInterface
{
    use LoggerAwareTrait;

    protected array $config = [];

    abstract public function __invoke(ServerRequestInterface $request): ResponseInterface;
}
