<?php

namespace BenTools\MercurePHP\Tests\Unit\Command;

use BenTools\MercurePHP\Command\ServeCommand;
use BenTools\MercurePHP\Configuration\Configuration;
use BenTools\MercurePHP\Hub\HubFactoryInterface;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function BenTools\MercurePHP\Tests\container;
use function BenTools\MercurePHP\without_nullish_values;
use function PHPUnit\Framework\assertStringContainsString;

it('serves a Mercure Hub', function () {
    $loop = container()->get(LoopInterface::class);
    $loop->addTimer(1.5, fn() => $loop->stop());
    $configuration = (new Configuration())->overrideWith(without_nullish_values($_SERVER));
    $factory = container()->get(HubFactoryInterface::class);
    $command = new ServeCommand($configuration, $factory, $loop, new NullLogger());
    $tester = new CommandTester($command);
    $tester->execute([
        '--jwt-key' => $_SERVER['JWT_KEY'],
        '--addr' => $_SERVER['ADDR'],
    ]);
    $output = $tester->getDisplay();
    assertStringContainsString('[info] Server running at http://' . $_SERVER['ADDR'], $output);
})->skip();
