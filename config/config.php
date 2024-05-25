<?php

return [
    'development' => true,
    'mongodb' => [
        'hosts' => [
            '127.0.0.1:27017',
        ]
    ],
    'redis' => [
        'host' =>'127.0.0.1',
        'port' => 6379,
        'password' => '',
        'database' => 0,
    ],
    'twig' => [
        'debug' => true,
        'autoReload' => true,
        'strictVariables' => false,
        'optimizations' => -1,
    ],
    'cloudflare' => [
        'token' => '',
    ],
    'fetcher' => [
        'user-agent' => 'EK/1.0',
    ],
    'esi' => [
        'user-agent' => 'EK/1.0',
        'global-rate-limit' => 500,
    ]
];