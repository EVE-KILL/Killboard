<?php

namespace EK\Logger;

class ExceptionLogger
{
    protected string $logDirectory = BASE_DIR . '/logs/';

    public function log(string $exceptionMessage, string $exceptionId): void
    {
        $logFile = $this->logDirectory . 'exceptions/' . $exceptionId . '.log';
        file_put_contents($logFile, $exceptionMessage);
    }
}