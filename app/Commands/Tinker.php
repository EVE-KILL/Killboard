<?php

namespace EK\Commands;

use Psy\Shell;
use Psy\Configuration;
use League\Container\Container;
use EK\Api\ConsoleCommand;

/**
 * @property $manualPath
 */
class Tinker extends ConsoleCommand
{
    protected string $signature = 'tinker { --manualPath=/usr/local/share/psysh : The path to store the PHP Manual }';
    protected string $description = 'Tinker with the PHP runtime itself using psysh';

    public function __construct(
        protected Container $container,
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        try {
            // Check for php_manual
            $manualPath = $this->manualPath;
            $manualName = 'php_manual.sqlite';
            if (!is_dir($manualPath) && !mkdir($manualPath, 0777, true) && !is_dir($manualPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $manualPath));
            }
            if (!file_exists($manualPath . '/' . $manualName)) {
                $this->out('Downloading PHP Manual, one moment...');
                copy('http://psysh.org/manual/en/php_manual.sqlite', $manualPath . '/' . $manualName);
            }
        } catch (\Exception $e) {
            $this->out("<bg=red>{$e->getMessage()}</>");
        }
        $shell = new Shell(new Configuration([]));
        $shell->setScopeVariables([
            'container' => $this->container,
        ]);

        $shell->run();
    }
}
