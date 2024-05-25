<?php

namespace EK\Commands;

use EK\Api\Abstracts\ConsoleCommand;
use Psy\Configuration;
use Psy\Shell;

/**
 * @property $manualPath
 */
class Tinker extends ConsoleCommand
{
    protected string $signature = 'tinker { --manualPath=/usr/local/share/psysh : The path to store the PHP Manual }';
    protected string $description = 'Tinker with the PHP runtime itself using psysh';

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
