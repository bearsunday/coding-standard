<?php

declare(strict_types=1);

// Bootstrap for PHPUnit — load Composer autoload, PHPCS autoload, and PHPCS token constants
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/squizlabs/php_codesniffer/autoload.php';
require_once __DIR__ . '/vendor/squizlabs/php_codesniffer/src/Util/Tokens.php';

// PHPCS requires these constants to be defined before Ruleset / Config are used
if (defined('PHP_CODESNIFFER_VERBOSITY') === false) {
    define('PHP_CODESNIFFER_VERBOSITY', 0);
}

if (defined('PHP_CODESNIFFER_CBF') === false) {
    define('PHP_CODESNIFFER_CBF', false);
}
