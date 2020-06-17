<?php

namespace BenTools\MercurePHP\Hub;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Controller\HealthController;
use BenTools\MercurePHP\Controller\PublishController;
use BenTools\MercurePHP\Controller\SubscribeController;
use BenTools\MercurePHP\Helpers\LoggerAwareTrait;
use BenTools\MercurePHP\Metrics\MetricsHandlerFactory;
use BenTools\MercurePHP\Metrics\MetricsHandlerFactoryInterface;
use BenTools\MercurePHP\Metrics\MetricsHandlerInterface;
use BenTools\MercurePHP\Metrics\PHP\PHPMetricsHandler;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Storage\StorageFactory;
use BenTools\MercurePHP\Storage\StorageFactoryInterface;
use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Transport\TransportFactory;
use BenTools\MercurePHP\Transport\TransportFactoryInterface;
use BenTools\MercurePHP\Transport\TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop;

use function Clue\React\Block\await;

final class HubFactory
{
    use LoggerAwareTrait;

    private array $config;
    private TransportFactoryInterface $transportFactory;
    private StorageFactoryInterface $storageFactory;
    private MetricsHandlerFactoryInterface $metricsHandlerFactory;

    public function __construct(
        array $config,
        ?LoggerInterface $logger = null,
        ?TransportFactoryInterface $transportFactory = null,
        ?StorageFactoryInterface $storageFactory = null,
        ?MetricsHandlerFactoryInterface $metricsHandlerFactory = null
    ) {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
        $this->transportFactory = $transportFactory ?? TransportFactory::default($config, $this->logger);
        $this->storageFactory = $storageFactory ?? StorageFactory::default($config, $this->logger);
        $this->metricsHandlerFactory = $metricsHandlerFactory ?? MetricsHandlerFactory::default($config, $this->logger);
    }

    public function create(EventLoop\LoopInterface $loop): Hub
    {
        $transport = $this->createTransport($loop);
        $storage = $this->createStorage($loop);
        $metricsHandler = $this->createMetricsHandler($loop);

        $subscriberAuthenticator = Authenticator::createSubscriberAuthenticator($this->config);
        $publisherAuthenticator = Authenticator::createPublisherAuthenticator($this->config);

        $controllers = [
            new HealthController(),
            (new SubscribeController($this->config, $subscriberAuthenticator))
                ->withLoop($loop)
                ->withTransport($transport)
                ->withStorage($storage)
                ->withLogger($this->logger())
            ,
            (new PublishController($publisherAuthenticator))
                ->withTransport($transport)
                ->withStorage($storage)
                ->withLogger($this->logger())
            ,
        ];

        $requestHandler = new RequestHandler($controllers);

        return new Hub($this->config, $requestHandler, $metricsHandler, $this->logger());
    }

    private function createTransport(EventLoop\LoopInterface $loop): TransportInterface
    {
        $factory = $this->transportFactory;
        $dsn = $this->config[Configuration::TRANSPORT_URL];

        if (!$factory->supports($dsn)) {
            throw new \RuntimeException(\sprintf('Invalid transport DSN %s', $dsn));
        }

        return await($factory->create($dsn, $loop), $loop);
    }

    private function createStorage(EventLoop\LoopInterface $loop): StorageInterface
    {
        $factory = $this->storageFactory;
        $dsn = $this->config[Configuration::STORAGE_URL]
            ?? $this->config[Configuration::TRANSPORT_URL];

        if (!$factory->supports($dsn)) {
            throw new \RuntimeException(\sprintf('Invalid storage DSN %s', $dsn));
        }

        return await($factory->create($dsn, $loop), $loop);
    }

    private function createMetricsHandler(EventLoop\LoopInterface $loop): MetricsHandlerInterface
    {
        $factory = $this->metricsHandlerFactory;
        $dsn = $this->config[Configuration::METRICS_URL]
            ?? $this->config[Configuration::STORAGE_URL]
            ?? $this->config[Configuration::TRANSPORT_URL];

        if (!$factory->supports($dsn)) {
            throw new \RuntimeException(\sprintf('Invalid metrics handler DSN %s', $dsn));
        }

        return await($factory->create($dsn, $loop), $loop);
    }
}
