<?php

namespace EK\Commands\Queue;

use EK\Api\Abstracts\ConsoleCommand;
use EK\RabbitMQ\RabbitMQ;
use League\Container\Container;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;

class Queue extends ConsoleCommand
{
    protected string $signature = 'queue {queue : Queue to listen on}';
    protected string $description = "";

    protected AMQPChannel $channel;
    protected AMQPStreamConnection $connection;

    public function __construct(
        protected Container $container,
        protected RabbitMQ $rabbitMQ,
        ?string $name = null
    ) {
        $this->channel = $this->rabbitMQ->getChannel();
        $this->connection = $this->rabbitMQ->getConnection();
        parent::__construct($name);
    }

    final public function handle(): void
    {
        $queueName = $this->queue;
        $this->out($this->formatOutput('<blue>Queue worker started</blue>: <green>' . $queueName . '</green>'));

        // Declare the queue with the correct parameters, including priority
        $this->channel->queue_declare($queueName, false, true, false, false, false, ['x-max-priority' => ['I', 10]]);

        $callback = function (AMQPMessage $msg) use ($queueName) {
            $startTime = microtime(true);
            $jobData = json_decode($msg->getBody(), true);
            $requeue = true;

            $className = $jobData["job"] ?? null;
            $data = $jobData["data"] ?? [];
            $sentryTrace = $jobData["sentry_trace"] ?? null;
            $baggage = $jobData["baggage"] ?? [];
            $runSentry = $sentryTrace !== null;

            if ($className === null) {
                $this->out($this->formatOutput('<red>Job error: Invalid job data</red>'));
                return;
            }

            if ($runSentry) {
                $context = \Sentry\continueTrace(
                    $sentryTrace,
                    $baggage
                )
                    ->setOp('queue.process')
                    ->setName($className);

                $transaction = \Sentry\startTransaction($context);
                \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);
            }

            try {
                $this->out($this->formatOutput('<yellow>Processing job: ' . $className . '</yellow>'));
                $this->out($this->formatOutput('<yellow>Job data: ' . json_encode($data) . '</yellow>'));

                // Load the instance and check if it should be requeued
                $instance = $this->container->get($className);
                $requeue = $instance->requeue ?? true;

                // Handle the job
                $instance->handle($data);

                $endTime = microtime(true);
                $this->out($this->formatOutput('<green>Job completed in ' . ($endTime - $startTime) . ' seconds</green>'));

                if ($runSentry) {
                    $transaction->setData([
                        'messaging.destination.name' => $queueName,
                        'messaging.message.body.size' => strlen($msg->getBody()),
                        'messaging.message.receive.latency' => ($endTime - $startTime) * 1000,
                        'messaging.message.retry.count' => $msg->get('application_headers')['x-death'][0]['count'] ?? 0,
                    ]);
                }

                // Acknowledge the message
                $msg->ack();
            } catch (\Exception $e) {
                if ($requeue) {
                    // Reject the message and requeue it
                    $msg->nack(true);
                    $this->out($this->formatOutput('<red>Job error (Requeued): ' . $e->getMessage() . '</red>'));
                } else {
                    // Reject the message without requeueing
                    $msg->nack(false);
                    $this->out($this->formatOutput('<red>Job error: ' . $e->getMessage() . '</red>'));
                }

                $transaction->setStatus(\Sentry\Tracing\SpanStatus::internalError());
            } finally {
                // Finish the span
                if ($runSentry) {
                    $transaction->finish();
                }
            }
        };

        // Set the prefetch count to 1
        $this->channel->basic_qos(null, 1, null);

        // Set up a consumer
        $this->channel->basic_consume($queueName, '', false, false, false, false, $callback);

        // Wait for incoming messages
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    private function formatOutput(string $message): string
    {
        $datetime = date('Y-m-d H:i:s');
        return "<blue>[{$datetime}]</blue> {$message}";
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
