<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use WebSocket\Client;

class EmitCharacterWS extends Jobs
{
    protected string $defaultQueue = 'websocket';

    public function handle(array $data): void
    {
        $client = new Client('wss://ws.eve-kill.com/characters');
        $client->text(json_encode([
            'type' => 'broadcast',
            'token' => 'my-secret',
            'data' => $data
        ]));
    }
}
