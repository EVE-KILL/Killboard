<?php

namespace EK\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;

class FileLogger implements LoggerInterface
{
    protected MonologLogger $logger;

    public function __construct(
        protected string $logFile = BASE_DIR . '/logs/request.log',
        protected string $loggerName = 'request-logger',
        protected Level $logLevel = Level::Debug
    )
    {
        $this->logger = new MonologLogger($this->loggerName);
        $this->logger->pushHandler(new StreamHandler($this->logFile, $this->logLevel));
    }

    public function __call($name, $arguments)
    {
        return $this->logger->$name(...$arguments);
    }

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
