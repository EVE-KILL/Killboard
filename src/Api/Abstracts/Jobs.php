<?php

namespace EK\Api\Abstracts;

use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

abstract class Jobs
{
    protected string $defaultQueue = 'low';
    public bool $requeue = true;
    protected AMQPChannel $channel;
    protected string $exchangeType = 'direct'; // Default to direct exchange
    protected string $defaultExchange = ''; // Default exchange for queues, '' for the default queue behavior

    public function __construct(
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
    ) {
        $this->channel = $this->rabbitMQ->getChannel();
    }

    /**
     * @param array $data The data to pass to the job
     * @param null|string $queue The queue to push the job to
     * @param int $processAfter Unix timestamp of when to process the job
     * @param string|null $exchange The exchange to use, null for default queue
     * @param array|string|null $routingKeys An array of routing keys or a single routing key for topics/exchanges
     * @param string|null $exchangeType The exchange type to use (e.g., 'direct', 'topic')
     * @return void
     */
    public function enqueue(array $data = [], ?string $queue = null, int $processAfter = 0, ?string $exchange = null, array|string $routingKeys = null, ?string $exchangeType = null): void
    {
        $queue = $queue ?? $this->defaultQueue;
        $exchange = $exchange ?? $this->defaultExchange;
        $exchangeType = $exchangeType ?? $this->exchangeType;

        if ($exchange) {
            // Declare the exchange with the specified type
            $this->channel->exchange_declare($exchange, $exchangeType, false, true, false);
        } else {
            // Declare the queue (standard queue)
            $this->channel->queue_declare($queue, false, true, false, false);
        }

        // Prepare job data
        $jobData = [
            'job' => get_class($this),
            'data' => $data,
            'process_after' => $processAfter,
        ];

        // Create AMQP message
        $messageBody = json_encode($jobData);
        $msg = new AMQPMessage($messageBody, ['delivery_mode' => 2]); // Make message persistent

        if (is_array($routingKeys)) {
            // If multiple routing keys are provided, publish to each
            foreach ($routingKeys as $routingKey) {
                $this->channel->basic_publish($msg, $exchange, $routingKey);
                $this->logger->debug("Job enqueued to {$exchange} with routing key {$routingKey}", $jobData);
            }
        } else {
            // If a single routing key is provided, publish normally
            $routingKey = $routingKeys ?? $queue; // Use queue name as routing key if not provided
            $this->channel->basic_publish($msg, $exchange, $routingKey);
            $this->logger->debug("Job enqueued to " . ($exchange ?: $queue) . " with routing key {$routingKey}", $jobData);
        }
    }

    public function massEnqueue(array $data = [], ?string $queue = null, int $processAfter = 0, ?string $exchange = null, array|string $routingKeys = null, ?string $exchangeType = null): void
    {
        $queue = $queue ?? $this->defaultQueue;
        $exchange = $exchange ?? $this->defaultExchange;
        $exchangeType = $exchangeType ?? $this->exchangeType;

        if ($exchange) {
            // Declare the exchange with the specified type
            $this->channel->exchange_declare($exchange, $exchangeType, false, true, false);
        } else {
            // Declare the queue (standard queue)
            $this->channel->queue_declare($queue, false, true, false, false);
        }

        $thisClass = get_class($this);

        foreach ($data as $d) {
            $jobData = [
                'job' => $thisClass,
                'data' => $d,
                'process_after' => $processAfter,
            ];

            $messageBody = json_encode($jobData);
            $msg = new AMQPMessage($messageBody, ['delivery_mode' => 2]); // Persistent message

            if (is_array($routingKeys)) {
                // If multiple routing keys are provided, publish to each
                foreach ($routingKeys as $routingKey) {
                    $this->channel->basic_publish($msg, $exchange, $routingKey);
                    $this->logger->debug("Job enqueued to {$exchange} with routing key {$routingKey}", $jobData);
                }
            } else {
                // If a single routing key is provided, publish normally
                $routingKey = $routingKeys ?? $queue; // Use queue name as routing key if not provided
                $this->channel->basic_publish($msg, $exchange, $routingKey);
                $this->logger->debug("Job enqueued to " . ($exchange ?: $queue) . " with routing key {$routingKey}", $jobData);
            }
        }
    }

    public function emptyQueue(?string $queue = null): void
    {
        $queue = $queue ?? $this->defaultQueue;

        // Declare the queue
        $this->channel->queue_declare($queue, false, true, false, false);

        // Purge the queue
        $this->channel->queue_purge($queue);
        $this->logger->debug("Queue {$queue} purged.");
    }

    abstract public function handle(array $data): void;
}
