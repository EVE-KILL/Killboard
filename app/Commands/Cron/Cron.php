<?php

namespace EK\Commands\Cron;

use EK\Api\ConsoleCommand;
use Kcs\ClassFinder\Finder\ComposerFinder;
use League\Container\Container;
use Poliander\Cron\CronExpression;

class Cron extends ConsoleCommand
{
    protected string $signature = 'cron';
    protected string $description = 'Run cron jobs';

    public function __construct(
        protected Container $container
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $finder = new ComposerFinder($this->autoloader);
        $finder->inNamespace('EK\\Cronjobs');

        foreach ($finder as $className => $reflection) {
            /** @var \EK\Cron\Api\Cronjob $instance */
            $instance = $this->container->get($className);
            $cronTime = $instance->getCronTime();

            $cronExpression = new CronExpression($cronTime);
            $shouldRun = $cronExpression->isMatching();

            if ($shouldRun === true) {
                try {
                    $this->out('Running cronjob: ' . $className);
                    $instance->handle();
                } catch (\Exception $e) {
                    $this->out("Error while running cron job {$className}: {$e->getMessage()}");
                }
            }
        }
    }
}
