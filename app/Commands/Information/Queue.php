<?php

namespace EK\Commands\Information;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Api\Attributes\RouteAttribute;
use EK\Redis\Redis;
use Kcs\ClassFinder\Finder\ComposerFinder;
use Predis\Client;
use ReflectionClass;
use RuntimeException;

class Queue extends ConsoleCommand
{
    public string $signature = 'info:queue { --json : Output as JSON }';
    public string $description = 'List all the information about the queue';
    protected Client $redisClient;
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
            'high',
            'character',
            'corporation',
            'alliance',
            'universe',
            'websocket',
            'killmail',
            'low',
            'default'
        ];

        // Get how many items are in the queue
        $queueInformation = [];
        foreach($queuesAvailable as $queue) {
            $queueInformation[$queue] = $this->redisClient->llen($queue);
        }

        if($this->json) {
            echo json_encode($queueInformation, JSON_PRETTY_PRINT);
            return;
        }

        $this->table(['Queue', 'Items'], $queueInformation);

    }
}
