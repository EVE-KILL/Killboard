<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Config\Config;
use EK\Redis\Redis;
use WebSocket\Client;

class EmitCharacterWS extends Jobs
{
    protected string $defaultQueue = 'websocket';

    public function __construct(
        protected Config $config,
        protected Redis $redis
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $client = new Client('wss://ws.eve-kill.com/characters');
        $client->text(json_encode([
            'type' => 'broadcast',
            'token' => $this->config->get('ws_token'),
            'data' => $data
        ]));
    }
}
