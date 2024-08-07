<?php

namespace EK\Commands\Queue;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Redis\Redis;
use League\Container\Container;
use OpenSwoole\Process\Pool;

/**
 * @property $manualPath
 */
class Queue extends ConsoleCommand
{
    protected string $signature = 'queue
        { --workers=4 : Number of queue workers }
        { --queues=high,websocket,killmail,character,corporation,alliance,universe,low,default,character_scrape : Queues to listen on (Default is high,low,default) }
    ';
    protected string $description = "Start the queue worker.";

    public function __construct(
        protected Redis $redis,
        protected Container $container,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        $this->out("Queue worker started");

        \OpenSwoole\Runtime::enableCoroutine(
            true,
            \OpenSwoole\Runtime::HOOK_ALL
        );

        $pool = new Pool($this->workers);
        $pool->set(["enable_coroutine" => true]);
        $queuesToListenOn = explode(",", $this->queues);

        $pool->on("WorkerStart", function ($pool, $workerId) use (
            $queuesToListenOn
        ) {
            $this->out("Worker {$workerId} started");
            $client = $this->redis->getClient();
            while (true) {
                // Listen on multiple queues
                foreach ($queuesToListenOn as $queue) {
                    list($queueName, $job) = $client->blpop($queue, 0.1);

                    if ($job !== null) {
                        $startTime = microtime(true);
                        $jobData = json_decode($job, true);
                        $requeue = true;

                        try {
                            $className = $jobData["job"] ?? null;
                            $data = $jobData["data"] ?? [];

                            if ($className === null) {
                                throw new \Exception("Job class not found");
                            }

                            $this->out(
                                "Processing job {$className} from {$queueName} ({$workerId})"
                            );
                            $this->out("Data: " . json_encode($data));

                            // Create a new instance of the job class
                            $instance = $this->container->get($className);
                            $instance->handle($data);

                            $endTime = microtime(true);
                            $requeue = $instance->requeue ?? true;

                            $this->out("Job completed in " . ($endTime - $startTime) . " seconds");
                            unset($instance);
                            continue 2;
                        } catch (\Exception $e) {
                            if ($requeue) {
                                $client->rpush($queueName, $job);
                                $this->out(
                                    "Job failed, pushed back to {$queueName}"
                                );
                                $this->out("Error: " . $e->getMessage());
                            } else {
                                $this->out("Error: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        });

        $pool->on("WorkerStop", function ($pool, $workerId) {
            $this->out("Worker {$workerId} stopped");
        });

        $pool->set([
            "daemonize" => false,
            "enable_coroutine" => true,
            "max_request" => 1000,
        ]);

        $pool->start();
    }
}
