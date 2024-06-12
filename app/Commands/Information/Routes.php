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
    public string $signature = 'info:routes { --format= : Output the routes as JSON / Table }';
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
                        $route = $prepend . $apiUrl->getRoute();

                        // Extract route parameters
                        preg_match_all('/\{(\w+)(:[^\}]+)?\}/', $route, $matches);
                        $parameters = $matches[1];

                        $endpoints[] = [
                            'route' => $route,
                            'method' => implode(',', $apiUrl->getType()),
                            'controller' => $className,
                            'action' => $method->getName(),
                            'parameters' => $parameters
                        ];
                    }

                    // Get the protected array $routes from the controller if it exists
                    $routes = $reflection->getDefaultProperties()['routes'] ?? [];
                    foreach ($routes as $route => $methods) {
                        $route = $prepend . $route;

                        // Extract route parameters
                        preg_match_all('/\{(\w+)(:[^\}]+)?\}/', $route, $matches);
                        $parameters = $matches[1];

                        $endpoints[] = [
                            'route' => $route,
                            'method' => implode(',', $methods),
                            'controller' => $className,
                            'action' => 'handle',
                            'parameters' => $parameters
                        ];
                    }
                }
            } catch (\Throwable $e) {
                throw new RuntimeException('Error loading controller: ' . $e->getMessage());
            }
        }

        switch ($this->format) {
            case 'json':
                $this->output->write(json_encode(array_map(function ($params) {
                    return [
                        'route' => $params['route'],
                        'method' => $params['method'],
                        'controller' => $params['controller'],
                        'action' => $params['action'],
                        'parameters' => $params['parameters']
                    ];
                }, $endpoints)));
                break;

            default:
                // Change all parameters into a json_encoded string
                foreach ($endpoints as $key => $endpoint) {
                    $endpoints[$key]['parameters'] = json_encode($endpoint['parameters']);
                }

                $this->table(['URL', 'Request Type', 'Class', 'Method', 'Parameters'], $endpoints);
                break;
        }
    }
}