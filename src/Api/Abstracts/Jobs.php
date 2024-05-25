<?php

namespace EK\Api\Abstracts;

use EK\Redis\Redis;
use Predis\Client;

abstract class Jobs
{
    protected array $queues = ['high', 'low', 'default'];
    protected Client $client;
    public function __construct(
        protected Redis $redis
    ) {
        $this->client = $redis->getClient();
    }

    /**
     * @param array $data The data to pass to the job
     * @param string $queue The queue to push the job to
     * @param int $processAfter Unix timestamp of when to process the job
     * @return void
     */
    public function enqueue(array $data = [], string $queue = 'low', int $processAfter = 0): void
    {
        $jobData = [
            'job' => get_class($this),
            'data' => $data,
            'process_after' => $processAfter,
        ];

        $this->client->rpush($queue, [json_encode($jobData)]);
    }

    abstract public function handle(array $data): void;
}