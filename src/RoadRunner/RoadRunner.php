<?php

namespace EK\RoadRunner;

use Nyholm\Psr7\Factory\Psr17Factory;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

class RoadRunner
{
    public function __construct(
    ) {
    }

    public function createWebWorker()
    {
        $psr17Factory = new Psr17Factory();
        $worker = Worker::create();
        $worker = new PSR7Worker($worker, $psr17Factory, $psr17Factory, $psr17Factory);

        return $worker;
    }
}
