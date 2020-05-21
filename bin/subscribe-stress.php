#!/usr/bin/env php
<?php

namespace BenTools\MercurePHP;

require __DIR__ . '/../vendor/autoload.php';

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use RingCentral\Psr7\Uri;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

$app = new SingleCommandApplication();
$app->setCode(
    function (InputInterface $input, OutputInterface $output): int {
        $output = new SymfonyStyle($input, $output);
        $start = time();

        if (null === $input->getOption('jwt-key')) {
            $output->error('No jwt-key provided.');

            return 1;
        }

        if (null === $input->getOption('topics')) {
            $output->error('No topics provided.');

            return 1;
        }

        $jwt = (new Builder())
            ->withClaim('mercure', ['subscribe' => ['*']])
            ->getToken(new Sha256(), new Key($input->getOption('jwt-key')));

        $client = HttpClient::create([
            'http_version' => '2.0',
            'verify_peer' => false,
            'verify_host' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $jwt,
            ],
        ]);

        $url = new Uri($input->getArgument('url'));
        $qs = '';
        $topics = \explode(',', $input->getOption('topics'));
        foreach ($topics as $topic) {
            $qs .= '&topic=' . $topic;
        }

        $url = $url->withQuery($qs);
        $nbSubscribers = (int) $input->getOption('subscribers');

        $requests = function (HttpClientInterface $client, string $url) use ($nbSubscribers) {
            for ($i = 0; $i < $nbSubscribers; $i++) {
                yield $client->request('GET', $url);
            }
        };

        $duration = (int) $input->getOption('duration');
        $times = [];
        try {
            foreach ($client->stream($requests($client, $url), $duration) as $response => $chunk) {
                $now = \time();
                $times[$now] ??= 0;
                $times[$now]++;

                if ($now >= ($start + $duration)) {
                    $response->cancel();
                }
            }
        } catch (TimeoutException | TransportException $e) {
        }

        $average = (int) \round(\array_sum($times) / \count($times));
        $output->success(\sprintf('Average messages /sec: %d', $average));

        return 0;
    }
);

$app->addArgument('url', InputArgument::REQUIRED, 'Mercure Hub url.');
$app->addOption('jwt-key', null, InputOption::VALUE_REQUIRED, 'Subscriber JWT KEY.');
$app->addOption('topics', null, InputOption::VALUE_REQUIRED, 'Topics to subscribe.');
$app->addOption('duration', null, InputOption::VALUE_OPTIONAL, 'Duration of the test, in seconds.', 10);
$app->addOption('subscribers', null, InputOption::VALUE_OPTIONAL, 'Number of subscribers', 100);

$app->run();
