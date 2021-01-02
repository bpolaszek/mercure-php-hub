<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$_SERVER['APP_ENV'] = \getenv('APP_ENV') ?: 'test';
(new Dotenv())->bootEnv(\dirname(__DIR__) . '/.env');
