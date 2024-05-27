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
        { --queues=high,character,corporation,alliance,universe,killmail,low,default : Queues to listen on (Default is high,low,default) }
    ';
    protected string $description = 'Start the queue worker.';

    public function __construct(
        protected Redis $redis,
        protected Container $container,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        $this->out('Queue worker started');

        $pool = new Pool($this->workers);
        $queuesToListenOn = explode(',', $this->queues);
        $suspend = false;

        pcntl_signal(SIGINT, function () use (&$suspend) {
            $this->out('SIGINT signal received, stopping workers');
            $suspend = true;
        });

        $pool->on('WorkerStart', function ($pool, $workerId) use ($queuesToListenOn, &$suspend) {
            $this->out("Worker {$workerId} started");
            $client = $this->redis->getClient();
            do {
                // Listen on multiple queues
                foreach ($queuesToListenOn as $queue) {
                    list($queueName, $job) = $client->blpop($queue, 0.25);

                    if ($job !== null) {
                        $startTime = microtime(true);
                        $jobData = json_decode($job, true);

                        try {
                            $className = $jobData['job'];
                            $data = $jobData['data'];
                            $processAfter = $jobData['process_after'];

                            $this->out("Processing job {$className} from {$queueName} ({$workerId})");

                            // If the job is scheduled for later, push it back to the queue
                            if ($processAfter > 0 && $processAfter > time()) {
                                $client->rpush($queueName, $job);
                                $this->out("Job scheduled for later, pushed back to {$queueName}");
                                continue 2;
                            }

                            // Create a new instance of the job class
                            $instance = $this->container->get($className);
                            $instance->handle($data);

                            $endTime = microtime(true);
                            $this->out("Job completed in " . ($endTime - $startTime) . " seconds");
                            continue 2;
                        } catch (\Exception $e) {
                            // If it fails, push it back to the queue it came from
                            $client->rpush($queueName, [json_encode($jobData)]);
                            $this->out("Job failed, pushed back to {$queueName}");
                            $this->out('Error: ' . $e->getMessage());
                        }
                    }
                }

                // Check if the signal was received after each job
                \Safe\pcntl_signal_dispatch();
            } while($suspend === false);

            $pool->shutdown();
        });

        $pool->on('WorkerStop', function ($pool, $workerId) {
            $this->out("Worker {$workerId} stopped");
        });

        $pool->set([
            'daemonize' => false,
            'enable_coroutine' => true
        ]);

        $pool->start();
    }
}
