<?php

namespace EK\Redis;

use EK\Config\Config;
use Predis\Client;

class Redis
{
    protected Client $client;

    public function __construct(
        protected Config $config
    ) {
        $this->client = new Client([
            'scheme' => 'tcp',
            'host' => $this->config->get('redis/host'),
            'port' => $this->config->get('redis/port'),
            'password' => $this->config->get('redis/password'),
            'database' => $this->config->get('redis/database'),
        ], [
            'prefix' => 'evekill:',
            'persistent' => true,
            'timeout' => 10,
            'read_write_timeout' => 5,
            'tcp_keepalive' => 1,
            'tcp_nodelay' => true,
            'throw_errors' => true,
        ]);
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}