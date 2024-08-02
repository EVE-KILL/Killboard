<?php

namespace EK\Api\Abstracts;

use EK\Logger\FileLogger;

abstract class Cronjob
{
    protected string $cronTime = '* * * * *';
    protected FileLogger $logger;

    public function __construct(
    ) {
        $this->logger = new FileLogger(BASE_DIR . '/logs/cron.log');
    }

    public function getCronTime(): string
    {
        return $this->cronTime;
    }

    abstract public function handle(): void;
}
