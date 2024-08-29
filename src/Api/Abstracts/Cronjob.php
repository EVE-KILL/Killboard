<?php

namespace EK\Api\Abstracts;

use EK\Logger\StdOutLogger;

abstract class Cronjob
{
    protected string $cronTime = '* * * * *';

    public function __construct(
        protected StdOutLogger $logger
    ) {
    }

    public function getCronTime(): string
    {
        return $this->cronTime;
    }

    abstract public function handle(): void;
}
