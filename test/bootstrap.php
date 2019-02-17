<?php

declare(strict_types=1);

require 'vendor/autoload.php';

// not autoloaded by Composer
require 'test/db/Users.php';
require 'test/db/LegacyUsers.php';
require 'test/db/ModernUsers.php';
require 'test/db/TestDbConnector.php';

$config = require 'test/config.php';

if (is_readable('test/config.user.php')) {
    $userConfig = require 'test/config.user.php';
    $config = array_replace_recursive($config, $userConfig);
}

theodorejb\Phaster\TestDbConnector::setConfig($config);
