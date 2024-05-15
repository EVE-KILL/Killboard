<?php

namespace EK\Commands;

use EK\Api\Attributes\RouteAttribute;
use EK\Api\ConsoleCommand;
use EK\Logger\Logger;
use Kcs\ClassFinder\Finder\ComposerFinder;
use League\Container\Container;
use OpenSwoole\Constant;
use OpenSwoole\Process;
use OpenSwoole\Server as OpenSwooleServer;
use Slim\Factory\AppFactory;

/**
 * @property $manualPath
 */
class Server extends ConsoleCommand
{
    protected string $signature = 'server
        { --host=127.0.0.1 : The host address to serve the application on. }
        { --port=9501 : The port to serve the application on. }
        { --workers=4 : The number of worker processes. }
    ';
    protected string $description = 'Launch the HTTP server.';

    public function __construct(
        protected Logger $logger,
        protected Container $container,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        // Start slim
        $app = AppFactory::create();

        // Load all routes
        $controllers = new ComposerFinder();
        $controllers->inNamespace('EK\\Controllers');

        foreach($controllers as $className => $reflection) {
            foreach($reflection->getMethods() as $method) {
                $attributes = $method->getAttributes(RouteAttribute::class);
                foreach($attributes as $attribute) {
                    $url = $attribute->newInstance();
                    $controller = $this->container->get($className);

                    $app->map($url->getType(), $url->getRoute(), $controller($method->getName()));
                }
            }
        }

        // Load middleware
        $middlewares = new ComposerFinder();
        $middlewares->inNamespace('EK\\Middlewares');

        foreach($middlewares as $className => $reflection) {
            $middleware = $this->container->get($className);
            $app->add($middleware);
        }

        $server = new \OpenSwoole\Http\Server($this->host, $this->port, \OpenSwoole\Server::POOL_MODE, Constant::SOCK_TCP);

        $server->on('start', function ($server) {
            $this->logger->info("Swoole http server is started at http://{$this->host}:{$this->port}");
        });

        $server->handle(function ($request) use ($app) {
            $response = $app->handle($request);
            return $response;
        });

        $server->set([
            'daemonize' => false,
            'worker_num' => $this->workers,
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'backlog' => 128,
            'reload_async' => true,
            'max_wait_time' => 60,
            'enable_coroutine' => true,
            'http_compression' => true,
            'http_compression_level' => 1,
            'buffer_output_size' => 4 * 1024 * 1024
        ]);

        $server->start();
    }
}
