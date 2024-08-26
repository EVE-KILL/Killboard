<?php

namespace EK\RoadRunner;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use Kcs\ClassFinder\Finder\ComposerFinder;
use League\Container\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use ReflectionClass;
use Slim\App;
use Slim\Factory\AppFactory;

class SlimFramework
{
    public function __construct(
        protected Container $container
    ) {
    }

    public function initialize(): App
    {
        // Initialize the Psr17Factory
        $psr17Factory = new Psr17Factory();

        // Initialize the Slim Framework App
        $slimFramework = AppFactory::create($psr17Factory, $this->container);

        // Load the routes
        $slimFramework = $this->loadRoutes($slimFramework);

        // Load the middleware
        $slimFramework = $this->loadMiddleware($slimFramework);

        return $slimFramework;
    }

    protected function loadRoutes(App $app): App
    {
        // Load the routes
        $controllers = new ComposerFinder();
        $controllers->inNamespace('EK\\Controllers');

        /** @var ReflectionClass $reflection */
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

        return $app;
    }

    protected function loadMiddleware(App $app): App
    {
        // Load the middleware
        $middlewares = new ComposerFinder();
        $middlewares->inNamespace('EK\\Middlewares');

        /** @var ReflectionClass $reflection */
        foreach($middlewares as $className => $reflection) {
            $middleware = $this->container->get($className);
            $app->add($middleware);
        }

        return $app;
    }
}
