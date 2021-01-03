<?php

namespace BenTools\MercurePHP\Hub;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Helpers\LoggerAwareTrait;
use BenTools\MercurePHP\Metrics\MetricsHandlerInterface;
use BenTools\MercurePHP\Security\CORS;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\Http;
use React\Promise\PromiseInterface;
use React\Socket;
use React\Socket\ConnectionInterface;

final class Hub
{
    use LoggerAwareTrait;

    private array $config;
    private LoopInterface $loop;
    private RequestHandler $requestHandler;
    private CORS $cors;
    private MetricsHandlerInterface $metricsHandler;
    private ?int $shutdownSignal;

    public function __construct(
        array $config,
        LoopInterface $loop,
        RequestHandler $requestHandler,
        MetricsHandlerInterface $metricsHandler,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->loop = $loop;
        $this->requestHandler = $requestHandler;
        $this->metricsHandler = $metricsHandler;
        $this->logger = $logger ?? new NullLogger();
        $this->cors = new CORS($config);
    }

    public function run(): void
    {
        $localAddress = $this->config[Configuration::ADDR];
        $this->shutdownSignal = null;
        $this->metricsHandler->resetUsers($localAddress);
        $this->loop->addSignal(SIGINT, function ($signal) {
            $this->stop($signal, $this->loop);
        });
        $this->loop->addPeriodicTimer(
            15,
            fn() => $this->metricsHandler->getNbUsers()->then(
                function (int $nbUsers) {
                    $memory = \memory_get_usage(true) / 1024 / 1024;
                    $this->logger()->debug("Users: {$nbUsers} - Memory: {$memory}MB");
                }
            )
        );

        $socket = $this->createSocketConnection($localAddress, $this->loop);
        $this->serve($localAddress, $socket, $this->loop);
    }

    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        return $this->requestHandler->handle($request)
            ->then(fn(ResponseInterface $response) => $this->cors->decorateResponse($request, $response));
    }

    private function createSocketConnection(string $localAddress, LoopInterface $loop): Socket\Server
    {
        $socket = new Socket\Server($localAddress, $loop);
        $socket->on('connection', function (ConnectionInterface $connection) use ($localAddress) {
            $this->metricsHandler->incrementUsers($localAddress);
            $connection->on('close', fn() => $this->metricsHandler->decrementUsers($localAddress));
        });

        return $socket;
    }

    private function serve(string $localAddress, Socket\Server $socket, LoopInterface $loop): void
    {
        $server = new Http\Server($loop, $this);
        $server->listen($socket);

        $this->logger()->info("Server running at http://" . $localAddress);
        $loop->run();
    }

    public function getShutdownSignal(): ?int
    {
        return $this->shutdownSignal;
    }

    private function stop(int $signal, LoopInterface $loop): void
    {
        $this->shutdownSignal = $signal;
        $loop->futureTick(function () use ($loop) {
            $loop->stop();
        });
    }
}
