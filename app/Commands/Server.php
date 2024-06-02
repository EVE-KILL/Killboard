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

        // Turn on all hooks
        \OpenSwoole\Runtime::enableCoroutine(true, \OpenSwoole\Runtime::HOOK_ALL);

        // Instantiate the server
        $server = new \OpenSwoole\Http\Server($this->host, $this->port, \OpenSwoole\Server::POOL_MODE, Constant::SOCK_TCP);

        $server->on('start', function ($server) {
            $this->logger->info("Swoole http server is started at http://{$this->host}:{$this->port}");
        });

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

        $serverSettings = [
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
            'buffer_output_size' => 32 * 1024 * 1024, // 32MB
        ];

        $this->tableOneRow($serverSettings);

        $server->set($serverSettings);
        $server->start();
    }
}
