<?php

namespace EK\Server;

use EK\Cache\Cache;
use EK\EVEKILL\DialHomeDevice;
use EK\Logger\Logger;
use Kcs\ClassFinder\Finder\ComposerFinder;
use League\Container\Container;
use OpenSwoole\Core\Psr\ServerRequest as Request;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

class Server
{
    protected array $options = [];

    public function __construct(
        protected Container $container,
        protected Logger $logger
    ) {

    }
    public function run(): void
    {
        // Start Slim
        $app = AppFactory::create();

        $app->get('/', function (Request $request, Response $response) {
            $response->getBody()->write('Please refer to https://esi.evetech.net/ui/ for the ESI documentation');
            return $response;
        });

        $server = new \OpenSwoole\Http\Server($this->options['host'], $this->options['port']);
        $server->on('start', function ($server) {
            $this->logger->log("Swoole http server is started at http://{$this->options['host']}:{$this->options['port']}");
        });

        $server->handle(function ($request) use ($app) {
            $response = $app->handle($request);

            $path = $request->getUri()->getPath();
            $requestParams = http_build_query($request->getQueryParams());
            $wasServedFromCache = $response->getHeader('X-EK-Cache')[0] === 'HIT';
            $this->logger->log("Request received: {$path}{$requestParams}", ['served-from-cache' => $wasServedFromCache]);

            return $response;
        });

        $server->set([
            'daemonize' => false,
            'worker_num' => $this->getOptions()['workers'] ?? 4,
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