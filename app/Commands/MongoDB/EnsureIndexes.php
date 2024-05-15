<?php

namespace EK\Commands\MongoDB;

use Composer\Autoload\ClassLoader;
use EK\Api\ConsoleCommand;
use Kcs\ClassFinder\Finder\ComposerFinder;
use League\Container\Container;

/**
 * @property $manualPath
 */
class EnsureIndexes extends ConsoleCommand
{
    protected string $signature = 'mongo:ensureIndexes';
    protected string $description = 'This ensures every model that has indexes listed, has those indexes created in the database.';

    public function __construct(
        protected Container $container,
        protected ClassLoader $autoloader
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $finder = new ComposerFinder($this->autoloader);
        $finder->inNamespace('EK\Models');

        foreach ($finder as $className => $reflection) {
            // @var \EK\Database\Collection $model
            $model = $this->container->get($className);
            $this->out('Ensuring indexes for ' . $className);
            $model->handleIndexes();
        }
    }
}
