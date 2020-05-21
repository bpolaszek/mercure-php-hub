<?php

namespace BenTools\MercurePHP\Hub;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Controller\AbstractController;
use BenTools\MercurePHP\Controller\HealthController;
use BenTools\MercurePHP\Controller\PublishController;
use BenTools\MercurePHP\Controller\SubscribeController;
use BenTools\MercurePHP\Helpers\LoggerAwareTrait;
use BenTools\MercurePHP\Metrics\InMemory\InMemoryMetricsHandler;
use BenTools\MercurePHP\Metrics\MetricsHandlerInterface;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Security\CORS;
use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Transport\TransportInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\Http;
use React\Promise\PromiseInterface;
use React\Socket;

use function React\Promise\resolve;

final class Hub
{
    use LoggerAwareTrait;

    private array $config;
    private LoopInterface $loop;
    private RequestHandlerInterface $requestHandler;
    private CORS $cors;
    private MetricsHandlerInterface $metricsHandler;
    private StorageInterface $storage;
    private TransportInterface $transport;

    /**
     * @var AbstractController[]
     */
    private array $controllers;

    public function __construct(array $config, LoopInterface $loop)
    {
        $this->config = $config;
        $this->loop = $loop;
        $this->logger = new NullLogger();
        $this->metricsHandler = new InMemoryMetricsHandler();
        $this->cors = new CORS($config);
    }

    public function run(): void
    {
        $this->init();
        $localAddress = $this->config[Configuration::ADDR];
        $socket = new Socket\Server($localAddress, $this->loop);
        $this->metricsHandler->resetUsers($localAddress);
        $this->loop->addPeriodicTimer(
            15,
            fn() => $this->metricsHandler->getNbUsers()->then(
                function (int $nbUsers) {
                    $memory = \memory_get_usage(true) / 1024 / 1024;
                    $this->logger()->debug("Users: {$nbUsers} - Memory: {$memory}MB");
                }
            )
        );
        $socket->on(
            'connection',
            function (Socket\ConnectionInterface $connection) use ($localAddress) {
                $this->metricsHandler->incrementUsers($localAddress);
                $connection->on('close', fn() => $this->metricsHandler->decrementUsers($localAddress));
            }
        );
        $server = new Http\Server($this);
        $server->listen($socket);
        $this->logger()->info("Server running at http://" . $localAddress);
        $this->loop->run();
    }

    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        return resolve(
            $this->cors->decorateResponse(
                $request,
                $this->requestHandler->handle($request)
            )
        );
    }

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

    public function withMetricsHandler(MetricsHandlerInterface $metricsHandler): self
    {
        $clone = clone $this;
        $clone->metricsHandler = $metricsHandler;

        return $clone;
    }

    public function withRequestHandler(RequestHandlerInterface $requestHandler): self
    {
        $clone = clone $this;
        $clone->requestHandler = $requestHandler;

        return $clone;
    }

    private function init(): void
    {
        if (!isset($this->loop)) {
            throw new \RuntimeException("Loop has not been set.");
        }
        if (!isset($this->transport)) {
            throw new \RuntimeException("Transport has not been set.");
        }
        if (!isset($this->storage)) {
            throw new \RuntimeException("Storage has not been set.");
        }

        $subscriberAuthenticator = Authenticator::createSubscriberAuthenticator($this->config);
        $publisherAuthenticator = Authenticator::createPublisherAuthenticator($this->config);

        $this->controllers = [
            new HealthController(),
            (new SubscribeController($this->config, $subscriberAuthenticator))
                ->withLoop($this->loop)
                ->withTransport($this->transport)
                ->withStorage($this->storage)
                ->withLogger($this->logger())
            ,
            (new PublishController($publisherAuthenticator))
                ->withTransport($this->transport)
                ->withStorage($this->storage)
                ->withLogger($this->logger())
            ,
        ];

        if (!isset($this->requestHandler)) {
            $this->requestHandler = new RequestHandler($this->controllers);
        }
    }
}
