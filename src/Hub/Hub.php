<?php

namespace BenTools\MercurePHP\Hub;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Helpers\LoggerAwareTrait;
use BenTools\MercurePHP\Metrics\MetricsHandlerInterface;
use BenTools\MercurePHP\Security\CORS;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\Http;
use React\Promise\PromiseInterface;
use React\Socket;
use React\Socket\ConnectionInterface;

use function React\Promise\resolve;

final class Hub implements RequestHandlerInterface
{
    use LoggerAwareTrait;

    private array $config;
    private RequestHandlerInterface $requestHandler;
    private CORS $cors;
    private MetricsHandlerInterface $metricsHandler;
    private ?int $shutdownSignal;

    public function __construct(
        array $config,
        RequestHandlerInterface $requestHandler,
        MetricsHandlerInterface $metricsHandler,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->requestHandler = $requestHandler;
        $this->metricsHandler = $metricsHandler;
        $this->logger = $logger ?? new NullLogger();
        $this->cors = new CORS($config);
    }

    public function run(LoopInterface $loop): void
    {
        $localAddress = $this->config[Configuration::ADDR];
        $this->shutdownSignal = null;
        $this->metricsHandler->resetUsers($localAddress);
        $loop->addSignal(SIGINT, function ($signal) use ($loop) {
            $this->stop($signal, $loop);
        });
        $loop->addPeriodicTimer(
            15,
            fn() => $this->metricsHandler->getNbUsers()->then(
                function (int $nbUsers) {
                    $memory = \memory_get_usage(true) / 1024 / 1024;
                    $this->logger()->debug("Users: {$nbUsers} - Memory: {$memory}MB");
                }
            )
        );

        $socket = $this->createSocketConnection($localAddress, $loop);
        $this->serve($localAddress, $socket, $loop);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->cors->decorateResponse(
            $request,
            $this->requestHandler->handle($request)
        );
    }

    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        return resolve($this->handle($request));
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
