<?php

declare(strict_types=1);

use VotingSystem\Config\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');
