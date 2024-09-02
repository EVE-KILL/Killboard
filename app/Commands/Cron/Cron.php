<?php

namespace EK\Commands\Cron;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Api\Abstracts\Cronjob;
use Kcs\ClassFinder\Finder\ComposerFinder;
use League\Container\Container;
use Poliander\Cron\CronExpression;
use Sentry\CheckInStatus;

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
        ini_set('memory_limit', '-1');
        $this->table(['Cronjob', 'Cron Time'], array_map(fn($cronjob) => [$cronjob['className'], $cronjob['cronTime']], $this->getCronjobs()));

        foreach($this->getCronjobs() as $cronjob) {
            $cronExpression = new CronExpression($cronjob['cronTime']);
            $shouldRun = $cronExpression->isMatching();

            if ($shouldRun === true) {
                try {
                    $sentryCheckinId = \Sentry\captureCheckIn(slug: $cronjob['className'], status: CheckInStatus::inProgress());
                    $this->out('Running cronjob: ' . $cronjob['className']);
                    $cronjob['instance']->handle();
                    \Sentry\captureCheckIn(slug: $cronjob['className'], status: CheckInStatus::ok(), checkInId: $sentryCheckinId);
                } catch (\Exception $e) {
                    $this->out("Error while running cron job {$cronjob['className']}: {$e->getMessage()}");
                }
            }
        }
    }

    protected function getCronjobs(): array
    {
        $cronjobs = [];

        $finder = new ComposerFinder($this->autoloader);
        $finder->inNamespace('EK\\Cronjobs');

        foreach ($finder as $className => $reflection) {
            /** @var Cronjob $instance */
            $instance = $this->container->get($className);
            $cronTime = $instance->getCronTime();

            $cronjobs[] = [
                'className' => $className,
                'cronTime' => $cronTime,
                'instance' => $instance
            ];
        }

        return $cronjobs;
    }
}
