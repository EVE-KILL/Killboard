<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Models\Killmails;
use EK\Redis\Redis;
use WebSocket\Client;

class emitKillmailWS extends Jobs
{
    protected string $defaultQueue = 'websocket';
    public function __construct(
        protected Killmails $killmails,
        protected \EK\Helpers\Killmails $killmailHelper,
        protected Redis $redis
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $client = new Client('wss://ws.eve-kill.com/kills');
        $client->text(json_encode([
            'type' => 'broadcast',
            'token' => 'my-secret',
            'data' => $data
        ]));
    }
}