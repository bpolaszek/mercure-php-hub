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

use function React\Promise\resolve;

final class Hub implements RequestHandlerInterface
{
    use LoggerAwareTrait;

    private array $config;
    private RequestHandlerInterface $requestHandler;
    private CORS $cors;
    private MetricsHandlerInterface $metricsHandler;

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
        $socket = new Socket\Server($localAddress, $loop);
        $this->metricsHandler->resetUsers($localAddress);
        $loop->addPeriodicTimer(
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
        $loop->run();
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
}
