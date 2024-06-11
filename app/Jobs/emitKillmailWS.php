<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Models\Killmails;
use EK\Redis\Redis;

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
        \Ratchet\Client\connect('wss://eve-kill.com/kills')->then(function ($conn) use ($data) {
            $conn->send(json_encode([
                'type' => 'broadcast',
                'token' => 'my-secret',
                'data' => $data
            ]));
            $conn->close();
        }, function ($e) {
            echo "Could not connect: {$e->getMessage()}\n";
        });
    }
}