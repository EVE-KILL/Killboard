<?php

namespace EK;

use Composer\Autoload\ClassLoader;
use EK\Config\Config;
use League\Container\Container;
use League\Container\ReflectionContainer;

class Bootstrap
{
    protected array $options = [];
    public function __construct(
        protected ClassLoader $autoloader,
        protected ?Container $container = null
    ) {
        $this->buildContainer();
    }

    protected function buildContainer(): void
    {
        $this->container = $this->container ?? new Container();

        // Default to elements being shared
        //$this->container->defaultToShared(true);

        // Register the reflection container
        $this->container->delegate(
            new ReflectionContainer(true)
        );

        // Register the config
        $this->container->add(Config::class)
            ->setShared(true);

        // Add the autoloader
        $this->container->add(ClassLoader::class, $this->autoloader);

        // Add the container to itself
        $this->container->add(Container::class, $this->container);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
