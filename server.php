<?php

// Users can pass --host and --port to the server.php script
$host = $argv[1] ?? '127.0.0.1';
$port = isset($argv[2]) ? (int) $argv[2] : 9501;

$server = require_once __DIR__ . '/src/init.php';
$server->run($host, $port);