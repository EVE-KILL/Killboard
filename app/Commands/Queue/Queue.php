<?php

namespace EK\Commands\Queue;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Redis\Redis;
use League\Container\Container;

/**
 * @property $manualPath
 */
class Queue extends ConsoleCommand
{
    protected string $signature = 'queue {queue : Queue to listen on}';
    protected string $description = "";

    public function __construct(
        protected Redis $redis,
        protected Container $container,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        $this->out($this->formatOutput('<blue>Queue worker started</blue>: <green>' . $this->queue . '</green>'));
        $run = true;
        $client = $this->redis->getClient();

        do {
            list($queueName, $job) = $client->blpop($this->queue, 0.1);
            if ($job !== null) {
                $startTime = microtime(true);
                $jobData = json_decode($job, true);
                $requeue = true;

                try {
                    $className = $jobData["job"] ?? null;
                    $data = $jobData["data"] ?? [];

                    if ($className !== null) {
                        $this->out($this->formatOutput('<yellow>Processing job: ' . $className . '</yellow>'));

                        // Load the instance and check if it should be requeued
                        $instance = $this->container->get($className);
                        $requeue = $instance->requeue ?? true;

                        // Handle the queue
                        $instance->handle($data);

                        $endTime = microtime(true);

                        $this->out($this->formatOutput('<green>Job completed in ' . ($endTime - $startTime) . ' seconds</green>'));
                    }
                } catch (\Exception $e) {
                    if ($requeue) {
                        $client->rpush($queueName, $job);
                        $this->out($this->formatOutput('<red>Job error (Requeued): ' . $e->getMessage() . '</red>'));
                    } else {
                        $this->out($this->formatOutput('<red>Job error: ' . $e->getMessage() . '</red>'));
                    }
                }
            }
        } while ($run);
    }

    private function formatOutput(string $message): string
    {
        $datetime = date('Y-m-d H:i:s');
        return "<blue>[{$datetime}]</blue> {$message}";
    }
}
