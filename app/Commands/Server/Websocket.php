<?php

namespace EK\Commands\Server;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Config\Config;
use EK\Logger\Logger;
use Kcs\ClassFinder\Finder\ComposerFinder;
use League\Container\Container;
use OpenSwoole\Constant;
use OpenSwoole\Table;

/**
 * @property $manualPath
 */
class Websocket extends ConsoleCommand
{
    protected string $signature = 'server:ws
        { --host=127.0.0.1 : The host address to serve the application on. }
        { --port=9201 : The port to serve the application on. }
        { --workers=4 : The number of worker processes. }
    ';
    protected string $description = 'Launch the HTTP server.';
    protected Table $wsClients;
    protected array $wsEndpoints;

    public function __construct(
        protected Logger $logger,
        protected Container $container,
        protected Config $config,
        ?string $name = null
    ) {
        parent::__construct($name);

        $this->wsClients = new Table(102400);
        $this->wsClients->column('id', Table::TYPE_INT, 4);
        $this->wsClients->column('data', Table::TYPE_STRING, 2048);
        $this->wsClients->column('endpoint', Table::TYPE_STRING, 2048);
        $this->wsClients->create();
    }

    final public function handle(): void
    {
        // Turn on all hooks
        \OpenSwoole\Runtime::enableCoroutine(true, \OpenSwoole\Runtime::HOOK_ALL);

        // Instantiate the server
        $server = new \OpenSwoole\WebSocket\Server($this->host, $this->port, \OpenSwoole\Server::POOL_MODE, Constant::SOCK_TCP);

        // Configure the websocket server
        $server = $this->configureWebSocket($server);

        // Show message once the server is started
        $server->on('start', function ($server) {
            $this->logger->info("Swoole websocket server is started at ws://{$this->host}:{$this->port}");
        });

        // Http configuration settings
        $serverSettings = [
            'daemonize' => false,
            'worker_num' => $this->workers,
            'max_request' => 1000000,
            'dispatch_mode' => 3,
            'backlog' => -1,
            'enable_coroutine' => true,
            'buffer_output_size' => 16 * 1024 * 1024, // 16MB

            // Websocket
            'websocket_compression' => true,
        ];

        // Emit the settings as a table
        $this->tableOneRow($serverSettings);

        // Set the settings
        $server->set($serverSettings);

        // Start and run the server
        $server->start();
    }

    private function configureWebSocket(\OpenSwoole\WebSocket\Server $server): \OpenSwoole\WebSocket\Server
    {
        // Load the websocket routes
        $websockets = new ComposerFinder();
        $websockets->inNamespace('EK\\Websockets');

        foreach($websockets as $className => $reflection) {
            $class = $this->container->get($className);
            $class->setServer($server);
            $this->wsEndpoints[$class->endpoint] = $class;
        }

        $server->on('open', function (\OpenSwoole\WebSocket\Server $server, $request) {
            $this->wsClients->set($request->fd, [
                'id' => $request->fd,
                'data' => json_encode($server->getClientInfo($request->fd), JSON_THROW_ON_ERROR, 512),
                'endpoint' => $request->server['request_uri']
            ]);
        });

        $server->on('close', function (\OpenSwoole\WebSocket\Server $server, $fd) {
            $this->wsClients->del($fd);
        });

        $server->on('message', function (\OpenSwoole\WebSocket\Server $server, $frame) {
            if (!json_validate($frame->data)) {
                $this->logger->error("Invalid JSON: {$frame->data}");
            }

            // [type => subscribe, data => [alliance_id => 123]]
            $frameData = json_decode($frame->data, true, 512, JSON_THROW_ON_ERROR);
            $type = $frameData['type'] ?? null;
            $data = $frameData['data'] ?? [];
            $token = $frameData['token'] ?? null;
            $fdData = $this->wsClients->get($frame->fd) ?? [];

            switch($type) {
                case 'subscribe':
                    if(!empty($this->wsEndpoints[$fdData['endpoint']])) {
                        $data = array_merge($data, ['connection_time' => time()]);
                        $this->wsEndpoints[$fdData['endpoint']]->subscribe($frame->fd, $data);
                    }
                    break;

                case 'unsubscribe':
                    if(!empty($this->wsEndpoints[$fdData['endpoint']])) {
                        $this->wsEndpoints[$fdData['endpoint']]->unsubscribe($frame->fd);
                    }
                    break;

                case 'broadcast':
                    if ($token === $this->config->get('ws_token')) {
                        try {
                            if (empty($fdData['endpoint'])) {
                                $server->push($frame->fd, json_encode(['error' => 'No endpoint provided']));
                            } else {
                                $this->wsEndpoints[$fdData['endpoint']]->handle($data);
                            }
                        } catch (\Exception $e) {
                            $this->logger->error($e->getMessage());
                        }
                    }
                    break;
            }
        });

        // Every 60 second emit how many are subscribed to each endpoint
        $server->tick(60000, function () use ($server) {
            $endpoints = [];
            foreach($this->wsClients as $fd => $client) {
                $endpoints[$client['endpoint']] = $endpoints[$client['endpoint']] ?? 0;
                $endpoints[$client['endpoint']]++;
            }

            $this->out('Websocket Subscriptions');
            $this->tableOneRow($endpoints);
        });

        // Every 60 seconds, clean up the wsClients table by checking if clients are still connected
        $server->tick(60000, function () {
            foreach($this->wsClients as $fd => $client) {
                if (!$this->wsClients->exists($fd)) {
                    $this->wsClients->del($fd);
                }
            }
        });

        return $server;
    }
}
