<?php

$autoloaderPath = dirname(__DIR__, 1) . '/vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    throw new \RuntimeException('Autoloader not found, please run composer install');
}

$autoloader = require_once $autoloaderPath;

// Add a global var to tell where the base dir is
if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__, 1));
}

// Ensure the cache folder exists
if (!file_exists(BASE_DIR . '/cache')) {
    @mkdir(BASE_DIR . '/cache', 0777, true);
}

return [new EK\Bootstrap($autoloader), $autoloader];
