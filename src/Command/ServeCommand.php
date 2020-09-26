<?php

namespace BenTools\MercurePHP\Command;

use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Hub\HubFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function BenTools\MercurePHP\nullify;
use function BenTools\MercurePHP\without_nullish_values;

final class ServeCommand extends Command
{
    protected static $defaultName = 'mercure:serve';

    private Configuration $configuration;
    private LoopInterface $loop;
    private ?LoggerInterface $logger;

    public function __construct(
        Configuration $configuration,
        ?LoopInterface $loop = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct();
        $this->configuration = $configuration;
        $this->loop = $loop ?? Factory::create();
        $this->logger = $logger;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loop = $this->loop;
        $output = new SymfonyStyle($input, $output);
        $logger = $this->logger ?? new ConsoleLogger($output, [LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL]);
        try {
            $config = $this->configuration->overrideWith(without_nullish_values($input->getOptions()))->asArray();
            $loop->futureTick(
                function () use ($config, $output) {
                    $this->displayConfiguration($config, $output);
                }
            );

            $hub = (new HubFactory($config, $loop, $logger))->create();
            $hub->run();

            if (\SIGINT === $hub->getShutdownSignal()) {
                $output->newLine(2);
                $output->writeln('SIGINT received. 😢');
                $output->writeln('Goodbye! 👋');

                return 0;
            }

            $output->error('Server process was killed unexpectedly.');

            return 1;
        } catch (\Exception $e) {
            $output->error($e->getMessage());

            return 1;
        }
    }

    protected function configure(): void
    {
        $this->setDescription('Runs the Mercure Hub as a standalone application.');
        $this->addOption(
            'addr',
            null,
            InputOption::VALUE_OPTIONAL,
            'The address to listen on.',
        )
            ->addOption(
                'transport-url',
                null,
                InputOption::VALUE_OPTIONAL,
                'The DSN to transport messages.',
            )
            ->addOption(
                'storage-url',
                null,
                InputOption::VALUE_OPTIONAL,
                'The DSN to store messages.',
            )
            ->addOption(
                'metrics-url',
                null,
                InputOption::VALUE_OPTIONAL,
                'The DSN to store metrics.',
            )
            ->addOption(
                'cors-allowed-origins',
                null,
                InputOption::VALUE_OPTIONAL,
                'A list of allowed CORS origins, can be * for all.',
            )
            ->addOption(
                'jwt-key',
                null,
                InputOption::VALUE_OPTIONAL,
                'The JWT key to use for both publishers and subscribers',
            )
            ->addOption(
                'jwt-algorithm',
                null,
                InputOption::VALUE_OPTIONAL,
                'The JWT verification algorithm to use for both publishers and subscribers, e.g. HS256 (default) or RS512.',
            )
            ->addOption(
                'publisher-jwt-key',
                null,
                InputOption::VALUE_OPTIONAL,
                'Must contain the secret key to valid publishers\' JWT, can be omitted if jwt_key is set.',
            )
            ->addOption(
                'publisher-jwt-algorithm',
                null,
                InputOption::VALUE_OPTIONAL,
                'The JWT verification algorithm to use for publishers, e.g. HS256 (default) or RS512.',
            )
            ->addOption(
                'subscriber-jwt-key',
                null,
                InputOption::VALUE_OPTIONAL,
                'Must contain the secret key to valid subscribers\' JWT, can be omitted if jwt_key is set.',
            )
            ->addOption(
                'subscriber-jwt-algorithm',
                null,
                InputOption::VALUE_OPTIONAL,
                'The JWT verification algorithm to use for subscribers, e.g. HS256 (default) or RS512.',
            )
            ->addOption(
                'allow-anonymous',
                null,
                InputOption::VALUE_NONE,
                'Allows subscribers with no valid JWT to connect.',
            );
    }

    private function displayConfiguration(array $config, SymfonyStyle $output): void
    {
        if (!$output->isVeryVerbose()) {
            return;
        }

        $rows = [];
        foreach ($config as $key => $value) {
            if (null === $value) {
                $value = '<fg=yellow>null</>';
            }
            if (\is_bool($value)) {
                $value = $value ? '<fg=green>true</>' : '<fg=red>false</>';
            }
            $rows[] = [$key, $value];
        }

        $output->table(['Key', 'Value'], $rows);
    }

    private function getInputOptions(InputInterface $input): array
    {
        return \array_filter(
            $input->getOptions(),
            fn($value) => null !== nullify($value) && false !== $value
        );
    }
}
