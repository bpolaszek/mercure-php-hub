<?php

namespace BenTools\MercurePHP\Tests\Classes;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class NullLoop implements LoopInterface
{
    public function addReadStream($stream, $listener)
    {
    }

    public function addWriteStream($stream, $listener)
    {
    }

    public function removeReadStream($stream)
    {
    }

    public function removeWriteStream($stream)
    {
    }

    public function addTimer($interval, $callback)
    {
    }

    public function addPeriodicTimer($interval, $callback)
    {
    }

    public function cancelTimer(TimerInterface $timer)
    {
    }

    public function futureTick($listener)
    {
    }

    public function addSignal($signal, $listener)
    {
    }

    public function removeSignal($signal, $listener)
    {
    }

    public function run()
    {
    }

    public function stop()
    {
    }
}
