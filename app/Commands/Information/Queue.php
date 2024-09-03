<?php

namespace EK\Commands\Information;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Redis\Redis;
use Redis as PhpRedis;

class Queue extends ConsoleCommand
{
    public string $signature = "info:queue { --json : Output as JSON }";
    public string $description = "List all the information about the queue";
    protected PhpRedis $redisClient;
    public function __construct(
        protected ClassLoader $autoloader,
        protected Redis $redis
    ) {
        parent::__construct();
        $this->redisClient = $redis->getClient();
    }

    final public function handle(): void
    {
        $queuesAvailable = [
            "high",
            "websocket",
            "killmail",
            "character",
            "corporation",
            "alliance",
            "universe",
            "low",
            "default",
            "character_scrape",
            "evewho",
            "meilisearch"
        ];

        // Get how many items are in the queue
        $queueInformation = [];
        foreach ($queuesAvailable as $queue) {
            $queueInformation[$queue] = $this->redisClient->llen($queue);
        }

        if ($this->json) {
            echo json_encode($queueInformation, JSON_PRETTY_PRINT);
            return;
        }

        $this->tableOneRow($queueInformation);
    }
}
