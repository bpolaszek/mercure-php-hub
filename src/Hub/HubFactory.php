<?php

namespace BenTools\MercurePHP\Hub;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Configuration\WithConfigTrait;
use BenTools\MercurePHP\Controller\AbstractController;
use BenTools\MercurePHP\Metrics\MetricsHandlerFactoryInterface;
use BenTools\MercurePHP\Metrics\MetricsHandlerInterface;
use BenTools\MercurePHP\Storage\StorageFactoryInterface;
use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Transport\TransportFactoryInterface;
use BenTools\MercurePHP\Transport\TransportInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

use function Clue\React\Block\await;

final class HubFactory implements HubFactoryInterface
{
    use WithConfigTrait;

    private LoopInterface $loop;
    private LoggerInterface $logger;
    private TransportFactoryInterface $transportFactory;
    private StorageFactoryInterface $storageFactory;
    private MetricsHandlerFactoryInterface $metricsHandlerFactory;

    /**
     * @var iterable|AbstractController[]
     */
    private iterable $controllers;

    public function __construct(
        Configuration $config,
        LoopInterface $loop,
        LoggerInterface $logger,
        TransportFactoryInterface $transportFactory,
        StorageFactoryInterface $storageFactory,
        MetricsHandlerFactoryInterface $metricsHandlerFactory,
        iterable $controllers
    ) {
        $this->config = $config->asArray();
        $this->loop = $loop;
        $this->logger = $logger;
        $this->transportFactory = $transportFactory;
        $this->storageFactory = $storageFactory;
        $this->metricsHandlerFactory = $metricsHandlerFactory;
        $this->controllers = $controllers;
    }

    public function create(): HubInterface
    {
        $transport = $this->createTransport();
        $storage = $this->createStorage();
        $metricsHandler = $this->createMetricsHandler();

        $controllers = \array_map(
            fn(AbstractController $controller) => $controller
                ->withTransport($transport)
                ->withStorage($storage)
                ->withConfig($this->config),
            \iterable_to_array($this->controllers)
        );

        $requestHandler = new RequestHandler($controllers);

        return new Hub($this->config, $this->loop, $requestHandler, $metricsHandler, $this->logger);
    }

    private function createTransport(): TransportInterface
    {
        $factory = $this->transportFactory;
        $dsn = $this->config[Configuration::TRANSPORT_URL];

        if (!$factory->supports($dsn)) {
            throw new \RuntimeException(\sprintf('Invalid transport DSN %s', $dsn));
        }

        return await($factory->create($dsn), $this->loop);
    }

    private function createStorage(): StorageInterface
    {
        $factory = $this->storageFactory;
        $dsn = $this->config[Configuration::STORAGE_URL]
            ?? $this->config[Configuration::TRANSPORT_URL];

        if (!$factory->supports($dsn)) {
            throw new \RuntimeException(\sprintf('Invalid storage DSN %s', $dsn));
        }

        return await($factory->create($dsn), $this->loop);
    }

    private function createMetricsHandler(): MetricsHandlerInterface
    {
        $factory = $this->metricsHandlerFactory;
        $dsn = $this->config[Configuration::METRICS_URL]
            ?? $this->config[Configuration::STORAGE_URL]
            ?? $this->config[Configuration::TRANSPORT_URL];

        if (!$factory->supports($dsn)) {
            throw new \RuntimeException(\sprintf('Invalid metrics handler DSN %s', $dsn));
        }

        return await($factory->create($dsn), $this->loop);
    }
}
