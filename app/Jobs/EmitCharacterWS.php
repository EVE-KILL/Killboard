<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Config\Config;
use WebSocket\Client;

class EmitCharacterWS extends Jobs
{
    protected string $defaultQueue = 'websocket';

    public function __construct(
        protected Config $config
    ) {

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
