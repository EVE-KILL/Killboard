<?php

namespace EK\Commands\Information;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Api\Attributes\RouteAttribute;
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
                // Extract the last part of the namespace
                $namespaceParts = explode('\\', $className);
                $prependNamespace = strtolower($namespaceParts[count($namespaceParts) - 2]);

                // If the namespace is 'EK\Controllers', don't prepend anything
                $prepend = $prependNamespace === 'controllers' ? '' : '/' . $prependNamespace;

                foreach ($reflection->getMethods() as $method) {
                    $attributes = $method->getAttributes(RouteAttribute::class);
                    foreach ($attributes as $attribute) {
                        $apiUrl = $attribute->newInstance();
                        $endpoints[] = [
                            $prepend . $apiUrl->getRoute(),
                            implode(',', $apiUrl->getType()),
                            $className,
                            $method->getName()
                        ];
                    }

                    // Get the protected array $routes from the controller if it exists
                    $routes = $reflection->getDefaultProperties()['routes'] ?? [];
                    foreach ($routes as $route => $methods) {
                        $endpoints[] = [
                            $prepend . $route,
                            implode(',', $methods),
                            $className,
                            'handle'
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