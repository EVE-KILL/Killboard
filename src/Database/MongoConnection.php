<?php

namespace EK\Database;

use EK\Config\Config;
use MongoDB\Client;

class MongoConnection
{
    protected Client $client;
    public function __construct(
        protected Config $config
    ) {
        $connectionString = "mongodb://{$this->config->get('MONGODB_HOSTS')}";
        $this->client = new Client($connectionString, [
            'options' => [
                'connectTimeoutMS' => 30000,
                'socketTimeoutMS' => 30000,
                'serverSelectionTimeoutMS' => 30000
            ],
            'typeMap' => [
                'array' => 'array',
                'document' => 'array',
                'root' => 'array',
            ],
            'db' => 'ESI'
        ], [
            'typeMap' => [
                'array' => 'array',
                'document' => 'array',
                'root' => 'array',
            ]
        ]);
    }
}