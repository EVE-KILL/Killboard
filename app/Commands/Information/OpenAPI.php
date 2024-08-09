<?php

namespace EK\Commands\Information;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Api\Attributes\RouteAttribute;
use FastRoute\RouteParser\Std;
use Kcs\ClassFinder\Finder\ComposerFinder;
use ReflectionClass;
use RuntimeException;

class OpenAPI extends ConsoleCommand
{
    public string $signature = 'info:openapi';
    public string $description = 'Generates OpenAPI JSON';

    public function __construct(
        protected ClassLoader $autoloader
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $openApi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'API Documentation',
                'version' => '1.0.0',
            ],
            'servers' => [
                [
                    'url' => 'https://eve-kill.com',
                    'description' => 'EVE-KILL API Server',
                ]
            ],
            'paths' => []
        ];

        // Controller Routes
        $controllers = new ComposerFinder();
        $controllers->inNamespace('EK\\Controllers');

        /** @var ReflectionClass $reflection */
        foreach ($controllers as $className => $reflection) {
            try {
                $namespaceParts = explode('\\', $className);
                $prependNamespace = strtolower($namespaceParts[count($namespaceParts) - 2]);
                $prepend = $prependNamespace === 'controllers' ? '' : '/' . $prependNamespace;

                foreach ($reflection->getMethods() as $method) {
                    $attributes = $method->getAttributes(RouteAttribute::class);
                    foreach ($attributes as $attribute) {
                        $apiUrl = $attribute->newInstance();
                        $route = $prepend . $apiUrl->getRoute();

                        // Parse the route
                        $routeParser = new Std();
                        $parsedRoutes = $routeParser->parse($route);

                        // Get the required and optional parameters
                        $required_parameters = [];
                        $optional_parameters = [];

                        foreach ($parsedRoutes[0] as $part) {
                            if (is_array($part)) {
                                $required_parameters[] = $part[0];
                            }
                        }

                        // Loop through the rest of the parsed routes to identify optional parameters
                        foreach ($parsedRoutes as $parsedRoute) {
                            foreach ($parsedRoute as $part) {
                                if (is_array($part)) {
                                    if (in_array($part[0], $required_parameters)) {
                                        continue; // Already identified as required
                                    }
                                    $optional_parameters[] = $part[0];
                                }
                            }
                        }

                        // Remove any duplicate optional parameters
                        $optional_parameters = array_unique($optional_parameters);

                        // Remove exact matches for [/]
                        $route = str_replace('[/]', '', $route);

                        // Step 1: Remove the patterns like :\d+ or -9+ and also handle trailing +
                        $route = preg_replace('/[:\-]\[?[a-zA-Z0-9\\\\]+\+?\]?|\+/', '', $route);

                        // Step 2: Remove the [ and ] brackets if they still exist
                        $route = str_replace(['[', ']'], '', $route);

                        // Add the route to the OpenAPI JSON
                        if (!isset($openApi['paths'][$route])) {
                            $openApi['paths'][$route] = [];
                        }

                        $openApi['paths'][$route][strtolower($apiUrl->getType()[0])] = [
                            'summary' => 'Generated API Endpoint',
                            // Needs to be more unique
                            'operationId' => $className . '::' . $method->getName(),
                            'tags' => [$prependNamespace],
                            'parameters' => $this->generateParameters($required_parameters, $optional_parameters),
                            'responses' => [
                                '200' => [
                                    'description' => 'Successful response',
                                ],
                            ],
                        ];
                    }
                }
            } catch (RuntimeException $e) {
                continue;
            }
        }

        $this->out(json_encode($openApi, JSON_PRETTY_PRINT));
    }

    protected function generateParameters(array $required, array $optional): array
    {
        $parameters = [];
        foreach ($required as $parameter) {
            $parameters[] = [
                'name' => $parameter,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                ],
            ];
        }

        foreach ($optional as $parameter) {
            $parameters[] = [
                'name' => $parameter,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                ],
            ];
        }

        return $parameters;
    }
}
