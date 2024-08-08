<?php

namespace EK\Api\Abstracts;

use EK\Logger\FileLogger;
use EK\Redis\Redis;
use Predis\Client;

abstract class Jobs
{
    protected array $queues = [
        'high',
        'characters',
        'corporations',
        'alliances',
        'universe',
        'killmails',
        'low',
        'default'
    ];
    protected string $defaultQueue = 'low';
    public bool $requeue = true;

    protected Client $client;
    protected FileLogger $logger;
    public function __construct(
        protected Redis $redis
    ) {
        $this->client = $redis->getClient();
        $this->logger = new FileLogger(
            BASE_DIR . '/logs/jobs.log',
            'queue-logger'
        );
    }

    /**
     * @param array $data The data to pass to the job
     * @param null|string $queue The queue to push the job to
     * @param int $processAfter Unix timestamp of when to process the job
     * @return void
     */
    public function enqueue(array $data = [], ?string $queue = null, int $processAfter = 0): void
    {
        $jobData = [
            'job' => get_class($this),
            'data' => $data,
            'process_after' => $processAfter,
        ];

        $this->client->rpush($queue ?? $this->defaultQueue, [json_encode($jobData)]);
    }

    public function massEnqueue(array $data = [], ?string $queue = null, int $processAfter = 0): void
    {
        $jobs = [];
        $thisClass = get_class($this);
        foreach ($data as $d) {
            $jobs[] = json_encode([
                'job' => $thisClass,
                'data' => $d,
                'process_after' => $processAfter,
            ]);
        }

        $this->client->rpush($queue ?? $this->defaultQueue, $jobs);
    }

    public function emptyQueue(?string $queue = null): void
    {
        $this->client->del($queue ?? $this->defaultQueue);
    }

    abstract public function handle(array $data): void;
}
