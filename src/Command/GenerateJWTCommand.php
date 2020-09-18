<?php

namespace BenTools\MercurePHP\Command;

use BenTools\MercurePHP\Configuration\Configuration;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function BenTools\MercurePHP\get_signer;

final class GenerateJWTCommand extends Command
{
    private const TARGET_PUBLISHERS = 'publishers';
    private const TARGET_SUBSCRIBERS = 'subscribers';
    private const TARGET_BOTH = 'both';
    private const VALID_TARGETS = [
        self::TARGET_PUBLISHERS,
        self::TARGET_SUBSCRIBERS,
        self::TARGET_BOTH,
    ];

    protected static $defaultName = 'mercure:jwt:generate';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = Configuration::bootstrapFromCLI($input)->asArray();

        $target = $input->getOption('target') ?? self::TARGET_BOTH;
        if (!\in_array($target, self::VALID_TARGETS, true)) {
            $io->error(\sprintf('Invalid target `%s`.', $target));

            return self::FAILURE;
        }

        $values = [
            'publish' => $input->getOption('publish'),
            'publish_exclude' => $input->getOption('publish-exclude'),
            'subscribe' => $input->getOption('subscribe'),
            'subscribe_exclude' => $input->getOption('subscribe-exclude'),
        ];

        $claim = [];

        if (\in_array($target, [self::TARGET_PUBLISHERS, self::TARGET_BOTH], true)) {
            $claim = [
                'publish' => $values['publish'],
                'publish_exclude' => $values['publish_exclude'],
            ];
        }

        if (\in_array($target, [self::TARGET_SUBSCRIBERS, self::TARGET_BOTH], true)) {
            $claim = \array_merge(
                $claim,
                [
                    'subscribe' => $values['subscribe'],
                    'subscribe_exclude' => $values['subscribe_exclude'],
                ]
            );
        }

        $claim = \array_filter($values, fn(array $claim) => [] !== $claim);
        $containsPublishTopics = isset($claim['publish']) || isset($claim['publish_exclude']);
        $containsSubscribeTopics = isset($claim['subscribe']) || isset($claim['subscribe_exclude']);
        $builder = (new Builder())->withClaim('mercure', $claim);

        if (null !== $input->getOption('ttl')) {
            $builder = $builder->expiresAt(\time() + (int) $input->getOption('ttl'));
        }

        $defaultKey = $config[Configuration::JWT_KEY];
        $defaultAlgorithm = $config[Configuration::JWT_ALGORITHM];

        if (isset($config[Configuration::PUBLISHER_JWT_KEY])) {
            $publisherKey = $config[Configuration::PUBLISHER_JWT_KEY];
            $publisherAlgorithm = $config[Configuration::PUBLISHER_JWT_ALGORITHM] ?? $config[Configuration::JWT_ALGORITHM];
        }

        if (isset($config[Configuration::SUBSCRIBER_JWT_KEY])) {
            $subscriberKey = $config[Configuration::SUBSCRIBER_JWT_KEY];
            $subscriberAlgorithm = $config[Configuration::SUBSCRIBER_JWT_ALGORITHM] ?? $config[Configuration::JWT_ALGORITHM];
        }

        if (true === $containsPublishTopics && false === $containsSubscribeTopics) {
            $target = self::TARGET_PUBLISHERS;
        } elseif (false === $containsPublishTopics && true === $containsSubscribeTopics) {
            $target = self::TARGET_SUBSCRIBERS;
        }

        switch ($target) {
            case self::TARGET_PUBLISHERS:
                $key = $publisherKey ?? $defaultKey;
                $algorithm = $publisherAlgorithm ?? $defaultAlgorithm;
                break;
            case self::TARGET_SUBSCRIBERS:
                $key = $subscriberKey ?? $defaultKey;
                $algorithm = $subscriberAlgorithm ?? $defaultAlgorithm;
                break;
            case self::TARGET_BOTH:
            default:
                $key = $defaultKey;
                $algorithm = $defaultAlgorithm;
        }

        try {
            $token = $builder->getToken(
                get_signer($algorithm),
                new Key($key),
            );
        } catch (\Exception $e) {
            $io->error('Unable to sign your token.');

            return self::FAILURE;
        }

        if (false === $input->getOption('raw')) {
            $io->success('Here is your token! ⤵️');
        }
        $output->writeln((string) $token);

        return self::SUCCESS;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $config = Configuration::bootstrapFromCLI($input)->asArray();
        $publishersOnly = !empty($config[Configuration::PUBLISHER_JWT_KEY]);
        $subscribersOnly = !empty($config[Configuration::SUBSCRIBER_JWT_KEY]);
        $forBothTargets = false === $publishersOnly && false === $subscribersOnly;

        if (!$forBothTargets && empty($input->getOption('target'))) {
            $value = $io->choice(
                'Do you want to generate a JWT for publishers or subscribers?',
                [
                    self::TARGET_PUBLISHERS,
                    self::TARGET_SUBSCRIBERS,
                ]
            );

            $input->setOption('target', $value);
        }

        if ($forBothTargets || self::TARGET_PUBLISHERS === $input->getOption('target')) {
            $values = (array) $input->getOption('publish');
            if (empty($values)) {
                ASK_PUBLISH:
                $value = $io->ask(
                    'Add a topic selector for the `publish` key (or just hit ENTER when you\'re done)'
                );
                if (null !== $value) {
                    $values[] = $value;
                    goto ASK_PUBLISH;
                }
                $input->setOption('publish', $values);
            }

            $values = (array) $input->getOption('publish-exclude');
            if (empty($values)) {
                ASK_PUBLISH_EXCLUDE:
                $value = $io->ask(
                    'Add a topic selector for the `publish-exclude` key (or just hit ENTER when you\'re done)'
                );
                if (null !== $value) {
                    $values[] = $value;
                    goto ASK_PUBLISH_EXCLUDE;
                }
                $input->setOption('publish-exclude', $values);
            }
        }

        if ($forBothTargets || self::TARGET_SUBSCRIBERS === $input->getOption('target')) {
            $values = (array) $input->getOption('subscribe');
            if (empty($values)) {
                ASK_SUBSCRIBE:
                $value = $io->ask(
                    'Add a topic selector for the `subscribe` key (or just hit ENTER when you\'re done)'
                );
                if (null !== $value) {
                    $values[] = $value;
                    goto ASK_SUBSCRIBE;
                }
                $input->setOption('subscribe', $values);
            }

            $values = (array) $input->getOption('subscribe-exclude');
            if (empty($values)) {
                ASK_SUBSCRIBE_EXCLUDE:
                $value = $io->ask(
                    'Add a topic selector for the `subscribe-exclude` key (or just hit ENTER when you\'re done)'
                );
                if (null !== $value) {
                    $values[] = $value;
                    goto ASK_SUBSCRIBE_EXCLUDE;
                }
                $input->setOption('subscribe-exclude', $values);
            }
        }

        if (null === $input->getOption('ttl')) {
            $value = $io->ask(
                'TTL of this token in seconds (or hit ENTER for no expiration):',
                null,
                function ($value) {
                    if (null === $value) {
                        return null;
                    }
                    if (!\is_numeric($value) || $value <= 0) {
                        throw new \RuntimeException('Invalid number.');
                    }

                    return $value;
                }
            );

            $input->setOption('ttl', $value);
        }
    }

    protected function configure(): void
    {
        $this->setDescription('Generates a JWT key to use on this hub.');
        $this
            ->addOption(
                'publish',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Allowed topic selectors for publishing.',
                []
            )
            ->addOption(
                'publish-exclude',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Denied topic selectors for publishing.',
                []
            )
            ->addOption(
                'subscribe',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Allowed topic selectors for subscribing.',
                []
            )
            ->addOption(
                'subscribe-exclude',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Denied topic selectors for subscribing.',
                []
            )
            ->addOption(
                'target',
                null,
                InputOption::VALUE_OPTIONAL
            )
            ->addOption(
                'ttl',
                null,
                InputOption::VALUE_OPTIONAL,
                'TTL of this token, in seconds.'
            )
            ->addOption(
                'raw',
                null,
                InputOption::VALUE_NONE,
                'Enable raw output'
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
            );
    }
}
