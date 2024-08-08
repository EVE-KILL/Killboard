<?php

namespace EK\Http;

use OpenSwoole\Table;
use OpenSwoole\Websocket\Server;

abstract class Websocket
{
    public string $endpoint = '';
    public Table $clients;
    public Server $server;

    public function __construct(
    ) {
        $this->clients = new Table(1024);
        $this->clients->column('fd', Table::TYPE_INT, 4);
        $this->clients->column('data', Table::TYPE_STRING, 2048);
        $this->clients->create();
    }

    public function setServer($server): void
    {
        $this->server = $server;
    }

    abstract public function handle(array $data): void;

    public function subscribe($fd, $data): void
    {
        $this->clients->set($fd, [
            'fd' => $fd,
            'data' => json_encode($data)
        ]);
    }

    public function unsubscribe($fd): void
    {
        $this->clients->del($fd);
    }

    public function sendAll($jsonData): void
    {
        foreach($this->clients as $fd => $client) {
            if($this->server->exists($fd)) {
                $this->server->push($fd, $jsonData, \OpenSwoole\WebSocket\Server::WEBSOCKET_OPCODE_TEXT, \OpenSwoole\WebSocket\Server::WEBSOCKET_FLAG_FIN | \OpenSwoole\WebSocket\Server::WEBSOCKET_FLAG_COMPRESS);
            } else {
                $this->unsubscribe($fd);
            }
        }
    }

    public function send($fd, $jsonData): void
    {
        if($this->server->exists($fd)) {
            $this->server->push($fd, $jsonData, \OpenSwoole\WebSocket\Server::WEBSOCKET_OPCODE_TEXT, \OpenSwoole\WebSocket\Server::WEBSOCKET_FLAG_FIN | \OpenSwoole\WebSocket\Server::WEBSOCKET_FLAG_COMPRESS);
        } else {
            $this->unsubscribe($fd);
        }
    }
}
