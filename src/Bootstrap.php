<?php

namespace EK;

use Composer\Autoload\ClassLoader;
use EK\Proxy\Proxy;
use League\Container\Container;
use League\Container\ReflectionContainer;
use OpenSwoole\Core\Psr\ServerRequest as Request;
use OpenSwoole\Http\Server;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

class Bootstrap
{
    public function __construct(
        protected ClassLoader $autoloader,
        protected ?Container $container = null
    ) {
        $this->buildContainer();
    }

    protected function buildContainer(): void
    {
        $this->container = $this->container ?? new Container();

        // Register the reflection container
        $this->container->delegate(
            new ReflectionContainer(true)
        );

        // Add the autoloader
        $this->container->add(ClassLoader::class, $this->autoloader)
            ->setShared(true);

        // Add the container to itself
        $this->container->add(Container::class, $this->container)
            ->setShared(true);
    }

    public function run(string $host = '127.0.0.1', int $port = 9501): void
    {
        // Start Slim
        $app = AppFactory::create();

        $app->get('/', function (Request $request, Response $response) {
            $response->getBody()->write('Please refer to https://esi.evetech.net/ui/ for the ESI documentation');
            return $response;
        });

        $app->get('/proxy/list', function (Request $request, Response $response) {
            $response->getBody()->write('List of proxies');
            return $response;
        });

        $app->get('/proxy/list/{id}', function (Request $request, Response $response, array $args) {
            $response->getBody()->write('Get proxy with id ' . $args['id']);
            return $response;
        });

        $app->map(['GET', 'POST'], '/proxy/add', function (Request $request, Response $response) {
            // If it's a get request, output a helpful message on how to post to this endpoint
            if ($request->getMethod() === 'GET') {
                $response->getBody()->write('Please POST to this endpoint with the following parameters: name, url');
                return $response;
            }

            // If it's a post request, get the post data
            $postData = json_decode($request->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

            // Add a new proxy
            /** @var Proxy $proxy */
            $proxy = $this->container->get(Proxy::class);
            $added = $proxy->addProxy(
                $postData->name,
                $postData->externalAddress,
                $postData->listen,
                $postData->port
            );

            if ($added) {
                $response->getBody()->write(json_encode(['success' => true, 'message' => 'Proxy added']));
            } else {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to add proxy']));
            }

            return $response;
        });

        // Catch-all route
        $app->get('/{routes:.+}', function (Request $request, Response $response) {
            $response->getBody()->write("Hello, World!");
            return $response;
        });

        $server = new Server($host, $port);
        $server->on('start', function ($server) use ($host, $port) {
            echo "Swoole http server is started at http://{$host}:{$port}\n";
        });

        $server->handle(function ($request) use ($app) {
            return $app->handle($request);
        });

        $server->start();
    }
}
