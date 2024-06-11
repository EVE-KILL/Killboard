<?php

namespace EK\Commands;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Logger\Logger;
use Kcs\ClassFinder\Finder\ComposerFinder;
use League\Container\Container;
use OpenSwoole\Constant;
use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Table;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

/**
 * @property $manualPath
 */
class Server extends ConsoleCommand
{
    protected string $signature = 'server
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
        ?string $name = null
    ) {
        parent::__construct($name);

        $this->wsClients = new Table(1024);
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

        // Configure the web server
        $server = $this->configureWebserver($server);

        // Configure the websocket server
        $server = $this->configureWebSocket($server);

        // Show message once the server is started
        $server->on('start', function ($server) {
            $this->logger->info("Swoole http server is started at http://{$this->host}:{$this->port}");
        });

        // Server configuration settings
        $serverSettings = [
            'daemonize' => false,
            'worker_num' => $this->workers,
            'max_request' => 1000000,
            'dispatch_mode' => 2,
            'backlog' => -1,
            'enable_coroutine' => true,
            'http_compression' => true,
            'http_compression_level' => 1,
            'buffer_output_size' => 4 * 1024 * 1024, // 4MB

            // Websocket
            'websocket_compression' => true,

            // Handle static files
            'enable_static_handler' => true,
            'document_root' => BASE_DIR . '/public',
        ];

        // Emit the settings as a table
        $this->tableOneRow($serverSettings);

        // Set the settings
        $server->set($serverSettings);

        // Start and run the server
        $server->start();
    }

    private function configureWebserver(\OpenSwoole\WebSocket\Server $server): \OpenSwoole\WebSocket\Server
    {
        // Start slim
        $app = AppFactory::create();

        // Load all routes
        $controllers = new ComposerFinder();
        $controllers->inNamespace('EK\\Controllers');

        foreach($controllers as $className => $reflection) {
            // Load the controller
            /** @var Controller $controller */
            $controller = $this->container->get($className);

            // Extract the last part of the namespace
            $namespaceParts = explode('\\', $className);
            $prependNamespace = strtolower($namespaceParts[count($namespaceParts) - 2]);

            // If the namespace is 'EK\Controllers', don't prepend anything
            $prepend = $prependNamespace === 'controllers' ? '' : '/' . $prependNamespace;

            // Add routes by attribute reflection
            foreach($reflection->getMethods() as $method) {
                $attributes = $method->getAttributes(RouteAttribute::class);
                foreach($attributes as $attribute) {
                    $url = $attribute->newInstance();
                    $app->map($url->getType(), $prepend . $url->getRoute(), $controller($method->getName()));
                }
            }

            // Add routes by the routes property
            foreach($controller->getRoutes() as $route => $methods) {
                // Check the controller has the handle method
                if(!method_exists($controller, 'handle')) {
                    throw new \Exception('Controller ' . $className . ' does not have a handle method.');
                }

                $app->map($methods, $prepend . $route, $controller('handle'));
            }
        }


        // Load middleware
        $middlewares = new ComposerFinder();
        $middlewares->inNamespace('EK\\Middlewares');

        foreach($middlewares as $className => $reflection) {
            $middleware = $this->container->get($className);
            $app->add($middleware);
        }

        // Add the slim app to the server
        $server->handle(function (ServerRequest $request) use ($app) {
            /** @var Response $response */
            $response = $app->handle($request);

            // Output log like nginx access log (Minus the timestamp)
            $this->logger->info(sprintf(
                '%s - %s "%s %s HTTP/%s" %s %s',
                $request->getServerParams()['remote_addr'],
                $request->getServerParams()['remote_user'] ?? '-',
                $request->getMethod(),
                $request->getUri()->getPath(),
                $request->getProtocolVersion(),
                $response->getStatusCode() ?? 200,
                $request->getServerParams()['content_length'] ?? 0
            ));

            return $response;
        });

        return $server;
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
                        $this->wsEndpoints[$fdData['endpoint']]->subscribe($frame->fd, $data);
                    }
                    break;

                case 'unsubscribe':
                    if(!empty($this->wsEndpoints[$fdData['endpoint']])) {
                        $this->wsEndpoints[$fdData['endpoint']]->unsubscribe($frame->fd);
                    }
                    break;

                case 'broadcast':
                    if ($token === 'my-secret') {
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
        return $server;
    }
}
