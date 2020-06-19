<?php

namespace BenTools\MercurePHP\Tests\Unit\Command;

use BenTools\MercurePHP\Command\ServeCommand;
use React\EventLoop\Factory;
use Symfony\Component\Console\Tester\CommandTester;

it('serves a Mercure Hub', function() {
    $loop = Factory::create();
    $loop->addTimer(0.5, fn() => $loop->stop());
    $command = new ServeCommand($loop);
    $tester = new CommandTester($command);
    $tester->execute([
        '--jwt-key' => \getenv('JWT_KEY'),
        '--addr' => \getenv('ADDR'),
    ]);
    $output = $tester->getDisplay();
    \assertTrue(0 === \strpos($output, '[info] Server running at http://' . \getenv('ADDR')));
});
