<?php

namespace EK\Websockets;

use EK\Http\Websocket;

class Characters extends Websocket
{
    public string $endpoint = '/characters';

    public function handle(array $data): void
    {
        dump($data);

        $this->sendAll(json_encode($data));
    }
}
