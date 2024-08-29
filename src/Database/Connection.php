<?php

namespace EK\Database;

use EK\Config\Config;
use MongoDB\Client;

class Connection
{
    public function __construct(
        protected Config $config
    ) {
    }

    public function getConnectionString(): string
    {
        $mongoDbHosts = $this->config->get('mongodb/hosts');
        return trim("mongodb://" . implode(',', $mongoDbHosts), ',');
    }

    public function getConnection(): Client
    {
        return new Client(
            $this->getConnectionString(),
            [
                'options' => [
                    'connectTimeoutMS' => 30000,
                    'socketTimeoutMS' => 30000,
                    'serverSelectionTimeoutMS' => 30000
                ],
                'typeMap' => [
                    'root' => 'object',
                    'document' => 'object',
                    'array' => 'object',
                ],
                'db' => 'esi'
            ],
            [
                'typeMap' => [
                    'array' => 'array',
                    'document' => 'array',
                    'root' => 'array',
                ]
            ]
        );
    }
}
