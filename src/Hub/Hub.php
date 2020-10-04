<?php

namespace BenTools\MercurePHP\Hub;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Controller\HealthController;
use BenTools\MercurePHP\Controller\PublishController;
use BenTools\MercurePHP\Controller\SubscribeController;
use BenTools\MercurePHP\Helpers\LoggerAwareTrait;
use BenTools\MercurePHP\Metrics\MetricsHandlerInterface;
use BenTools\MercurePHP\Model\Message;
use BenTools\MercurePHP\Model\Subscription;
use BenTools\MercurePHP\Security\Authenticator;
use BenTools\MercurePHP\Security\CORS;
use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Storage\StorageInterface;
use BenTools\MercurePHP\Transport\TransportInterface;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use React\EventLoop\LoopInterface;
use React\Http;
use React\Promise\PromiseInterface;
use React\Socket;
use React\Socket\ConnectionInterface;

use function BenTools\MercurePHP\get_client_id;
use function React\Promise\all;
use function React\Promise\any;
use function React\Promise\resolve;
use function Symfony\Component\String\u;

final class Hub implements RequestHandlerInterface
{
    use LoggerAwareTrait;

    private array $config;
    private LoopInterface $loop;
    private TransportInterface $transport;
    private StorageInterface $storage;
    private RequestHandlerInterface $requestHandler;
    private CORS $cors;
    private MetricsHandlerInterface $metricsHandler;
    private ?int $shutdownSignal;

    public function __construct(
        array $config,
        LoopInterface $loop,
        TransportInterface $transport,
        StorageInterface $storage,
        MetricsHandlerInterface $metricsHandler,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->loop = $loop;
        $this->transport = $transport;
        $this->storage = $storage;
        $this->metricsHandler = $metricsHandler;
        $this->logger = $logger ?? new NullLogger();
        $this->cors = new CORS($config);

        $subscriberAuthenticator = Authenticator::createSubscriberAuthenticator($this->config);
        $publisherAuthenticator = Authenticator::createPublisherAuthenticator($this->config);

        $controllers = [
            new HealthController(),
            new SubscribeController(
                $this->config,
                $this,
                $subscriberAuthenticator,
                $this->logger()
            ),
            new PublishController($this, $publisherAuthenticator),
        ];

        $this->requestHandler = new RequestHandler($controllers);
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

    public function hook(callable $callback): void
    {
        $this->loop->futureTick($callback);
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

    public function subscribe(
        string $subscriber,
        string $topicSelector,
        ?Token $token,
        callable $callback
    ): PromiseInterface {
        $allowAnonymous = $this->config[Configuration::ALLOW_ANONYMOUS];

        if (!TopicMatcher::canSubscribeToTopic($topicSelector, $token, $allowAnonymous)) {
            $this->logger()->debug("Client {$subscriber} cannot subscribe to {$topicSelector}");

            return resolve($topicSelector);
        }

        $this->logger()->debug("Client {$subscriber} subscribed to {$topicSelector}");
        return $this->transport->subscribe($topicSelector, $callback);
    }

    public function publish(string $topic, Message $message): PromiseInterface
    {
        return $this->transport->publish($topic, $message)
            ->then(fn() => $this->storage->storeMessage($topic, $message))
            ->then(
                function () use ($topic, $message) {
                    $this->logger()->debug(\sprintf('Created message %s on topic %s', $message->getId(), $topic));
                }
            );
    }

    public function dispatchSubscriptions(array $subscriptions): PromiseInterface
    {
        return $this->storage->storeSubscriptions($subscriptions)
            ->then(
                function () use ($subscriptions) {
                    $promises = [];
                    foreach ($subscriptions as $subscription) {
                        $promises[] = $this->transport->publish(
                            $subscription->getId(),
                            new Message(
                                (string) Uuid::uuid4(),
                                \json_encode($subscription, \JSON_THROW_ON_ERROR),
                                true
                            )
                        );
                    }

                    return all($promises);
                }
            );
    }

    public function fetchMissedMessages(?string $lastEventID, array $subscribedTopics): PromiseInterface
    {
        if (null === $lastEventID) {
            return resolve([]);
        }

        return $this->storage->retrieveMessagesAfterId($lastEventID, $subscribedTopics);
    }

    private function createSocketConnection(string $localAddress, LoopInterface $loop): Socket\Server
    {
        $socket = new Socket\Server($localAddress, $loop);
        $socket->on('connection', function (ConnectionInterface $connection) use ($localAddress) {
            $this->metricsHandler->incrementUsers($localAddress);
            $connection->on('close', fn() => $this->handleClosingConnection($connection, $localAddress));
        });

        return $socket;
    }

    private function handleClosingConnection(ConnectionInterface $connection, string $localAddress): PromiseInterface
    {
        $this->metricsHandler->decrementUsers($localAddress);
        [$remoteHost, $remotePort] = u($connection->getRemoteAddress())->after('//')->split(':');
        $subscriber = get_client_id((string) $remoteHost, (int) (string) $remotePort);
        return $this->storage->findSubscriptionsBySubscriber($subscriber)
            ->then(fn(iterable $subscriptions) => $this->dispatchUnsubscriptions($subscriptions));
    }

    /**
     * @param Subscription[] $subscriptions
     */
    private function dispatchUnsubscriptions(iterable $subscriptions): PromiseInterface
    {
        $promises = [];
        foreach ($subscriptions as $subscription) {
            $subscription->setActive(false);
            $message = new Message(
                (string) Uuid::uuid4(),
                \json_encode($subscription, \JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
                true,
            );
            $topic = $subscription->getId();
            $promises[] = $this->transport->publish($topic, $message);
        }

        $promises[] = $this->storage->removeSubscriptions($subscriptions);

        return any($promises);
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
