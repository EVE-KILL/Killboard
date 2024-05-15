<?php

namespace EK\Console;

use Kcs\ClassFinder\Finder\ComposerFinder;
use RuntimeException;
use Symfony\Component\Console\Application;

class Console
{
    public function __construct(
        public \Psr\Container\ContainerInterface $container,
        public \Composer\Autoload\ClassLoader $autoloader,
        public ?\Symfony\Component\Console\Application $console = null,
        protected string $commandsNamespace = 'EK\\Commands',
        protected string $consoleName = 'EK',
        protected string $version = '1.0.0'
    ) {
    }

    public function run()
    {
        // Load the console if no console is given
        $this->console = $this->console ?? new Application($this->consoleName, $this->version);

        // Load the commands using KCS composer finder
        $finder = new ComposerFinder($this->autoloader);
        $finder->inNamespace($this->commandsNamespace);

        // Add all the commands found to the container
        foreach ($finder as $className => $reflection) {
            $this->console->add($this->container->get($className));
        }

        // Run the console
        try {
            $this->console->run();
        } catch (\Exception $e) {
            throw new RuntimeException("Error while running the console: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    public function getConsole(): ?\Symfony\Component\Console\Application
    {
        return $this->console;
    }

    public function setCommandsNamespace(string $namespace): void
    {
        $this->commandsNamespace = $namespace;
    }

    public function getCommandsNamespace(): string
    {
        return $this->commandsNamespace;
    }

    public function setConsoleName(string $name): void
    {
        $this->consoleName = $name;
    }

    public function getConsoleName(): string
    {
        return $this->consoleName;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
