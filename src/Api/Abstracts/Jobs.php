<?php

namespace EK\Api\Abstracts;

use EK\Logger\FileLogger;
use EK\RabbitMQ\RabbitMQ;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

abstract class Jobs
{
    protected string $defaultQueue = 'low';
    public bool $requeue = true;
    protected FileLogger $logger;
    protected AMQPChannel $channel;

    public function __construct(
        protected RabbitMQ $rabbitMQ
    ) {
        // Logger setup
        $this->logger = new FileLogger(
            BASE_DIR . '/logs/jobs.log',
            'queue-logger'
        );

        $this->channel = $this->rabbitMQ->getChannel();
    }

    /**
     * @param array $data The data to pass to the job
     * @param null|string $queue The queue to push the job to
     * @param int $processAfter Unix timestamp of when to process the job
     * @return void
     */
    public function enqueue(array $data = [], ?string $queue = null, int $processAfter = 0): void
    {
        $queue = $queue ?? $this->defaultQueue;

        // Declare the queue
        $this->channel->queue_declare($queue, false, true, false, false);

        // Prepare job data
        $jobData = [
            'job' => get_class($this),
            'data' => $data,
            'process_after' => $processAfter,
        ];

        // Create AMQP message
        $messageBody = json_encode($jobData);
        $msg = new AMQPMessage($messageBody, ['delivery_mode' => 2]); // Make message persistent

        // Publish to the queue
        $this->channel->basic_publish($msg, '', $queue);

        $this->logger->info("Job enqueued to {$queue}", $jobData);
    }

    public function massEnqueue(array $data = [], ?string $queue = null, int $processAfter = 0): void
    {
        $queue = $queue ?? $this->defaultQueue;

        // Declare the queue
        $this->channel->queue_declare($queue, false, true, false, false);

        $thisClass = get_class($this);

        foreach ($data as $d) {
            $jobData = [
                'job' => $thisClass,
                'data' => $d,
                'process_after' => $processAfter,
            ];

            $messageBody = json_encode($jobData);
            $msg = new AMQPMessage($messageBody, ['delivery_mode' => 2]); // Persistent message

            // Publish to the queue
            $this->channel->basic_publish($msg, '', $queue);
            $this->logger->info("Job enqueued to {$queue}", $jobData);
        }
    }

    public function emptyQueue(?string $queue = null): void
    {
        $queue = $queue ?? $this->defaultQueue;

        // Declare the queue
        $this->channel->queue_declare($queue, false, true, false, false);

        // Purge the queue
        $this->channel->queue_purge($queue);
        $this->logger->info("Queue {$queue} purged.");
    }

    abstract public function handle(array $data): void;
}
