<?php

declare(strict_types=1);

use theodorejb\Phaster\Test\DbConnector;

require 'vendor/autoload.php';

$config = require 'test/config.php';

if (is_readable('test/config.user.php')) {
    /** @psalm-suppress MissingFile */
    $userConfig = require 'test/config.user.php';
    $config = array_replace_recursive($config, $userConfig);
}

DbConnector::setConfig($config);
