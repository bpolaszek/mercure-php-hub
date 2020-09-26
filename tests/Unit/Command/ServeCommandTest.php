<?php

namespace BenTools\MercurePHP\Tests\Unit\Command;

use BenTools\MercurePHP\Command\ServeCommand;
use BenTools\MercurePHP\Configuration\Configuration;
use React\EventLoop\Factory;
use Symfony\Component\Console\Tester\CommandTester;

use function BenTools\MercurePHP\without_nullish_values;

it('serves a Mercure Hub', function () {
    $loop = Factory::create();
    $loop->addTimer(0.5, fn() => $loop->stop());
    $configuration = (new Configuration())->overrideWith(without_nullish_values($_SERVER));
    $command = new ServeCommand($configuration, $loop);
    $tester = new CommandTester($command);
    $tester->execute([
        '--jwt-key' => \getenv('JWT_KEY'),
        '--addr' => \getenv('ADDR'),
    ]);
    $output = $tester->getDisplay();
    \assertStringContainsString('[info] Server running at http://' . \getenv('ADDR'), $output);
});
