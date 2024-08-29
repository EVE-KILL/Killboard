<?php

namespace EK\Logger;

use EK\Models\Logs;
use Psr\Log\LoggerInterface;
use Stringable;

class Logger implements LoggerInterface
{
    public function __construct(
        protected Logs $logs
    ) {
    }

    public function log($level, string|Stringable $message, array $context = []) {
        $this->insertLog((string) $message, $level, $context);
    }

    protected function insertLog(string $message, string $level = 'INFO', array $context = []): void
    {
        $this->logs->collection->insertOne([
            'message' => $message,
            'level' => $level,
            'context' => $context,
        ]);
    }

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->insertLog((string) $message, 'EMERGENCY', $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->insertLog((string) $message, 'ALERT', $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->insertLog((string) $message, 'CRITICAL', $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->insertLog((string) $message, 'ERROR', $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->insertLog((string) $message, 'WARNING', $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->insertLog((string) $message, 'NOTICE', $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->insertLog((string) $message, 'INFO', $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->insertLog((string) $message, 'DEBUG', $context);
    }
}
