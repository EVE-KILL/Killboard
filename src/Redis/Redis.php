<?php

namespace EK\Redis;

use EK\Config\Config;
use \Redis as PhpRedis;

class Redis
{
    protected PhpRedis $client;

    public function __construct(
        protected Config $config
    ) {
        $this->client = new PhpRedis();

        // Establish a persistent connection to Redis
        $this->client->pconnect(
            $this->config->get('redis/host'),
            $this->config->get('redis/port'),
            10, // timeout in seconds
            null, // persistent_id, null by default
            0, // retry_interval, 0 means no retry
            0 // read_timeout, 0 means infinite
        );

        // Set password if provided
        $password = $this->config->get('redis/password');
        if ($password) {
            $this->client->auth($password);
        }

        // Select the database
        $this->client->select($this->config->get('redis/database'));

        // Set options
        $this->client->setOption(PhpRedis::OPT_PREFIX, 'evekill:');
        $this->client->setOption(PhpRedis::OPT_SERIALIZER, 2);
        $this->client->setOption(PhpRedis::OPT_READ_TIMEOUT, 30);
        $this->client->setOption(PhpRedis::OPT_TCP_KEEPALIVE, 1);
    }

    public function getClient(): PhpRedis
    {
        return $this->client;
    }
}
