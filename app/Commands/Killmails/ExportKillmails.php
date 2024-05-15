<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\ConsoleCommand;
use EK\Models\Killmails;

class ExportKillmails extends ConsoleCommand
{
    public string $signature = 'export:killmails';
    public string $description = 'Export all the killmails to a compress JSON blob in the resources/ dir';

    public function __construct(
        protected ClassLoader $autoloader,
        protected killmails $killmails,
    ) {
        parent::__construct();
    }


    final public function handle(): void
    {
        // We don't need any memory limits where we're going
        ini_set('memory_limit', '-1');

        // Get the total count of killmails
        $this->out('Please wait, getting killmail count..');
        $killmailCount = $this->killmails->count();
        $this->out("Exporting {$killmailCount} killmails");

        // Chunk the calls to MongoDB, and get 1000000 pr. call and write to the JSON
        $iterations = 0;
        $totalIterations = ceil($killmailCount / 1000000);

        // Add the array to the file, but make it a continuous JSON output
        $this->out("Total iterations: {$totalIterations}");

        do {
            $killmails = $this->killmails->find([], [
                'limit' => 1000000,
                'skip' => $iterations * 1000000,
                'sort' => ['killID' => 1],
                'projection' => ['_id' => 0, 'killID' => 1, 'hash' => 1]
            ]);
            $this->out('.', false);
            $file = BASE_DIR . '/resources/killmails-' . $iterations . '.json';
            file_put_contents($file, json_encode($killmails->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_BIGINT_AS_STRING) . "\n", FILE_APPEND);
            exec("gzip {$file}");

            $iterations++;
        } while($iterations < $totalIterations);

        $this->out("Done");
    }
}
