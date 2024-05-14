<?php

namespace EK\Logger;

use Monolog\Level;
use Monolog\Logger as MonologLogger;

class Logger
{
    protected MonologLogger $logger;

    public function __construct(
        protected Level $level = Level::Debug
    )
    {
        $this->logger = new MonologLogger('http-server');
        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout'), $this->level);
    }

    public function log(string $message, array $context = []): void
    {
        $this->logger->log($this->level, $message, $context);
    }
}
