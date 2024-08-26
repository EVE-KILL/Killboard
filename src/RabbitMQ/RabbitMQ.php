<?php

namespace EK\RabbitMQ;

use EK\Config\Config;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQ
{
    protected AMQPStreamConnection $connection;
    protected AMQPChannel $channel;

    public function __construct(
        protected Config $config
    ) {
        // Establish RabbitMQ connection
        $this->connection = new AMQPStreamConnection(
            $this->config->get('rabbitmq/host'),
            $this->config->get('rabbitmq/port'),
            $this->config->get('rabbitmq/user'),
            $this->config->get('rabbitmq/password'),
            connection_timeout: 10,
            read_write_timeout: 10
        );
        $this->channel = $this->connection->channel();
    }

    public function getChannel(): AMQPChannel
    {
        return $this->channel;
    }

    public function getConnection(): AMQPStreamConnection
    {
        return $this->connection;
    }
}
