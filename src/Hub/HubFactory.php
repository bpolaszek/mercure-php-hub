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
use BenTools\MercurePHP\Metrics\PHP\PHPMetricsHandlerFactory;
use BenTools\MercurePHP\Metrics\Redis\RedisMetricsHandlerFactory;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Storage\NullStorage\NullStorageFactory;
use BenTools\MercurePHP\Storage\PHP\PHPStorageFactory;
use BenTools\MercurePHP\Storage\Redis\RedisStorageFactory;
use BenTools\MercurePHP\Storage\StorageFactory;
use BenTools\MercurePHP\Storage\StorageFactoryInterface;
use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Transport\PHP\PHPTransportFactory;
use BenTools\MercurePHP\Transport\Redis\RedisTransportFactory;
use BenTools\MercurePHP\Transport\TransportFactory;
use BenTools\MercurePHP\Transport\TransportFactoryInterface;
use BenTools\MercurePHP\Transport\TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;

use function Clue\React\Block\await;

final class HubFactory
{
    use LoggerAwareTrait;

    private array $config;
    private LoopInterface $loop;
    private ?TransportFactoryInterface $transportFactory;
    private ?StorageFactoryInterface $storageFactory;
    private ?MetricsHandlerFactoryInterface $metricsHandlerFactory;

    public function __construct(
        array $config,
        LoopInterface $loop,
        ?LoggerInterface $logger = null,
        ?TransportFactoryInterface $transportFactory = null,
        ?StorageFactoryInterface $storageFactory = null,
        ?MetricsHandlerFactoryInterface $metricsHandlerFactory = null
    ) {
        $this->config = $config;
        $this->loop = $loop;
        $this->logger = $logger ?? new NullLogger();
        $this->transportFactory = $transportFactory;
        $this->storageFactory = $storageFactory;
        $this->metricsHandlerFactory = $metricsHandlerFactory;
    }

    public function create(): Hub
    {
        $transport = $this->createTransport($this->loop);
        $storage = $this->createStorage($this->loop);
        $metricsHandler = $this->createMetricsHandler($this->loop);

        $subscriberAuthenticator = Authenticator::createSubscriberAuthenticator($this->config);
        $publisherAuthenticator = Authenticator::createPublisherAuthenticator($this->config);

        $controllers = [
            new HealthController(),
            (new SubscribeController($this->config, $subscriberAuthenticator, $this->loop))
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

        return new Hub($this->config, $this->loop, $requestHandler, $metricsHandler, $this->logger());
    }

    private function createTransport(LoopInterface $loop): TransportInterface
    {
        $factory = $this->transportFactory ?? $this->getDefaultTransportFactory($loop);
        $dsn = $this->config[Configuration::TRANSPORT_URL];

        if (!$factory->supports($dsn)) {
            throw new \RuntimeException(\sprintf('Invalid transport DSN %s', $dsn));
        }

        return await($factory->create($dsn), $loop);
    }

    private function getDefaultTransportFactory(LoopInterface $loop): TransportFactory
    {
        return new TransportFactory([
            new RedisTransportFactory($loop, $this->logger()),
            new PHPTransportFactory(),
        ]);
    }

    private function createStorage(LoopInterface $loop): StorageInterface
    {
        $factory = $this->storageFactory ?? $this->getDefaultStorageFactory($loop);
        $dsn = $this->config[Configuration::STORAGE_URL]
            ?? $this->config[Configuration::TRANSPORT_URL];

        if (!$factory->supports($dsn)) {
            throw new \RuntimeException(\sprintf('Invalid storage DSN %s', $dsn));
        }

        return await($factory->create($dsn), $loop);
    }

    private function getDefaultStorageFactory(LoopInterface $loop): StorageFactory
    {
        return new StorageFactory([
            new RedisStorageFactory($loop, $this->logger()),
            new PHPStorageFactory(),
            new NullStorageFactory(),
        ]);
    }

    private function createMetricsHandler(LoopInterface $loop): MetricsHandlerInterface
    {
        $factory = $this->metricsHandlerFactory ?? $this->getDefaultMetricsHandlerFactory($loop);
        $dsn = $this->config[Configuration::METRICS_URL]
            ?? $this->config[Configuration::STORAGE_URL]
            ?? $this->config[Configuration::TRANSPORT_URL];

        if (!$factory->supports($dsn)) {
            throw new \RuntimeException(\sprintf('Invalid metrics handler DSN %s', $dsn));
        }

        return await($factory->create($dsn), $loop);
    }

    private function getDefaultMetricsHandlerFactory(LoopInterface $loop): MetricsHandlerFactory
    {
        return new MetricsHandlerFactory([
            new PHPMetricsHandlerFactory(),
            new RedisMetricsHandlerFactory($loop, $this->logger()),
        ]);
    }
}
