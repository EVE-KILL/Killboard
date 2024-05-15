<?php

namespace EK\Database;

use EK\Config\MongoDB;
use EK\Server\Server;
use MongoDB\Client;

class Connection
{
    public function __construct(
        protected Server $server,
    ) {
    }

    public function getConnectionString(): string
    {
        return "mongodb://127.0.0.1:27017";
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
