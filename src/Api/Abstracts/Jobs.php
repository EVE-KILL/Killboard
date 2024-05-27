<?php

namespace EK\Api\Abstracts;

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

    protected Client $client;
    public function __construct(
        protected Redis $redis
    ) {
        $this->client = $redis->getClient();
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

    abstract public function handle(array $data): void;
}