<?php

//ini_set('memory_limit', '2048M');

$autoloaderPath = dirname(__DIR__, 1) . '/vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    throw new \RuntimeException('Autoloader not found, please run composer install');
}

$autoloader = require_once $autoloaderPath;

// Load Sentry
$sentryDsn = getenv('SENTRY_DSN', false);
if ($sentryDsn !== false) {
    \Sentry\init([
        'dsn' => getenv('SENTRY_DSN'),
        'traces_sample_rate' => 0.2,
        'traces_sampler' => function (\Sentry\Tracing\SamplingContext $context): float {
            // Return a random number between 0 and 1
            return mt_rand() / mt_getrandmax();
        },
        'profiles_sample_rate' => 1.0,
    ]);
}

// Add a global var to tell where the base dir is
if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__, 1));
}

// Ensure the cache folder exists
if (!file_exists(BASE_DIR . '/cache')) {
    @mkdir(BASE_DIR . '/cache', 0777, true);
}

return [new EK\Bootstrap($autoloader), $autoloader];
