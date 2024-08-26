<?php

namespace EK\Commands\Server;

use EK\Api\Abstracts\ConsoleCommand;
use EK\RoadRunner\RoadRunner as RoadRunnerRoadRunner;
use EK\RoadRunner\SlimFramework;

/**
 * @property $manualPath
 */
class RoadRunner extends ConsoleCommand
{
    protected string $signature = 'server:roadrunner';
    protected string $description = 'Launch the RoadRunner HTTP Server.';

    public function __construct(
        protected RoadRunnerRoadRunner $roadRunner,
        protected SlimFramework $slimFramework,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        $app = $this->slimFramework->initialize();
        $worker = $this->roadRunner->createWebWorker();

        // Find out if we have an RR_MODE in env, if we do we are in the right context, otherwise throw an error - since this command should never be run without RoadRunner doing the running.
        if(!isset($_SERVER['RR_MODE'])) {
            $this->out('<red>[ERROR]</red> This command should only be run in the context of RoadRunner.');
            exit(0);
        }

        while($request = $worker->waitRequest()) {
            try {
                $response = $app->handle($request);
                $worker->respond($response);
            } catch(\Throwable $e) {
                $worker->getWorker()->error((string) $e);
            }
        }
    }
}
