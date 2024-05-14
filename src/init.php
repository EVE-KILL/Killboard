<?php

$autoloaderPath = dirname(__DIR__, 1) . '/vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    throw new \RuntimeException('Autoloader not found, please run composer install');
}

$autoloader = require_once $autoloaderPath;

return new EK\Bootstrap($autoloader);
