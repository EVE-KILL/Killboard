<?php

namespace EK\Api;

use EK\Console\ConsoleHelper;

abstract class ConsoleCommand extends ConsoleHelper
{
    /**
     * Signature of the command
     *
     * @example console { --manualPath=/usr/local/share/psysh : The path to store the PHP Manual }
     *
     * @var string
     */
    protected string $signature;

    /**
     * Description of the command
     *
     * @example console command to tinker with the framework
     *
     * @var string
     */
    protected string $description;

    /**
     * Is the command hidden from the list view?
     *
     * @var bool
     */
    protected bool $hidden = false;

    abstract public function handle(): void;
}
