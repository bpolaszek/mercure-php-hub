<?php

namespace BenTools\MercurePHP\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class DebugConfigCommand extends Command
{
    const CONFIGURATION_KEYS = [
        'addr',
        'transport_url',
        'storage_url',
        'metrics_url',
        'cors_allowed_origins',
        'publish_allowed_origins',
        'jwt_key',
        'jwt_algorithm',
        'publisher_jwt_key',
        'publisher_jwt_algorithm',
        'subscriber_jwt_key',
        'subscriber_jwt_algorithm',
        'allow_anonymous',
    ];

    protected static $defaultName = 'mercure:debug:config';

    private ParameterBagInterface $params;
    private ?string $addr;

    public function __construct(ParameterBagInterface $params, ?string $addr = null)
    {
        parent::__construct();
        $this->params = $params;
        $this->addr = $addr;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keys = self::CONFIGURATION_KEYS;
        $rows = [];
        foreach ($keys as $key) {
            $value = $this->params->get($key);
            if (null === $value) {
                $value = '<fg=yellow>null</>';
            }
            if (\is_bool($value)) {
                $value = $value ? '<fg=green>true</>' : '<fg=red>false</>';
            }
            if ('' === $value) {
                $value = '<fg=white>\'\'</>';
            }
            $rows[] = [$key, $value];
        }

        $io->table(['Key', 'Value'], $rows);

        return self::SUCCESS;
    }
}
