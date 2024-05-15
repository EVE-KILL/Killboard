<?php

namespace EK\Commands\Routes;

use Composer\Autoload\ClassLoader;
use EK\Api\ConsoleCommand;
use EK\Http\Attributes\RouteAttribute;
use Kcs\ClassFinder\Finder\ComposerFinder;
use ReflectionClass;
use RuntimeException;

class Routes extends ConsoleCommand
{
    public string $signature = 'info:routes { --json : Output as JSON }';
    public string $description = 'List all the routes available for the server';

    public function __construct(
        protected ClassLoader $autoloader
    ) {
        parent::__construct();
    }


    final public function handle(): void
    {
        $endpoints = [];

        // Controller Routes
        $controllers = new ComposerFinder();
        $controllers->inNamespace('EK\\Controllers');

        /** @var ReflectionClass $reflection */
        foreach ($controllers as $className => $reflection) {
            try {
                foreach ($reflection->getMethods() as $method) {
                    $attributes = $method->getAttributes(RouteAttribute::class);
                    foreach ($attributes as $attribute) {
                        $apiUrl = $attribute->newInstance();
                        $endpoints[] = [
                            $apiUrl->getRoute(),
                            implode(',', $apiUrl->getType()),
                            $className,
                            $method->getName()
                        ];
                    }
                }
            } catch (\Throwable $e) {
                throw new RuntimeException('Error loading controller: ' . $e->getMessage());
            }
        }

        if ($this->json === true) {
            $this->output->write(json_encode(array_map(function ($params) {
                return [
                    'route' => $params[0],
                    'method' => $params[1],
                    'controller' => $params[2],
                    'action' => $params[3]
                ];
            }, $endpoints)));
            exit(0);
        } else {
            $this->table(['URL', 'Request Type', 'Class', 'Method'], $endpoints);
        }
    }
}
