<?php

namespace EK\Api\Abstracts;

use EK\Logger\Logger;

abstract class Cronjob
{
    protected string $cronTime = '* * * * *';

    public function __construct(
        protected Logger $logger
    ) {
    }

    public function getCronTime(): string
    {
        return $this->cronTime;
    }

    abstract public function handle(): void;
}