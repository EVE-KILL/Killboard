<?php

return [
    'development' => true,
    'mongodb' => [
        'hosts' => [
            '127.0.0.1:27017',
        ]
    ],
    'redis' => [
        'hosts' => [
            '127.0.0.1:6379',
        ],
    ],
    'twig' => [
        'debug' => true,
        'autoReload' => true,
        'strictVariables' => false,
        'optimizations' => -1,
    ]
];