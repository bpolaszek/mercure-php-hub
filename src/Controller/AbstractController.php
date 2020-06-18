<?php

namespace BenTools\MercurePHP\Controller;

use BenTools\MercurePHP\Helpers\LoggerAwareTrait;
use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Transport\TransportInterface;
use BenTools\Psr7\RequestMatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;

abstract class AbstractController implements RequestMatcherInterface
{
    use LoggerAwareTrait;

    protected array $config = [];
    protected ?TransportInterface $transport;
    protected ?StorageInterface $storage;

    abstract public function __invoke(ServerRequestInterface $request): ResponseInterface;

    public function withTransport(TransportInterface $transport): self
    {
        $clone = clone $this;
        $clone->transport = $transport;

        return $clone;
    }

    public function withStorage(StorageInterface $storage): self
    {
        $clone = clone $this;
        $clone->storage = $storage;

        return $clone;
    }
}
