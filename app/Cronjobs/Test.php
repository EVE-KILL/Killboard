<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;

class Test extends Cronjob
{
    public function handle(): void
    {
        $this->logger->info('Test cronjob ran');
    }
}