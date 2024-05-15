<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\ConsoleCommand;
use EK\Models\Killmails;
use MongoDB\Driver\BulkWrite;

class ImportKillmails extends ConsoleCommand
{
    public string $signature = 'import:killmails';
    public string $description = 'Import killmail data from JSON blobs';

    public function __construct(
        protected ClassLoader $autoloader,
        protected killmails $killmails,
    ) {
        parent::__construct();
    }

    private function processKillmails($backup) {
        $json = gzdecode(file_get_contents($backup));
        $killmails = json_decode($json, true);
        $seenHashes = [];
        foreach($killmails as $killmail) {
            if (!isset($seenHashes[$killmail['hash']])) {
                // Replace killmail_id with killID
                $killmail['killID'] = $killmail['killmail_id'];
                unset($killmail['killmail_id']);
                $seenHashes[$killmail['hash']] = true;
                yield $killmail;
            }
        }
    }
    final public function handle(): void
    {
        ini_set('memory_limit', '-1');

        $backups = glob(BASE_DIR . '/resources/killmails-*.json.gz');
        // Sort by numbers 0,1,2,3,4 instead of 0,1,10
        usort($backups, function($a, $b) {
            return strnatcmp($a, $b);
        });

        foreach($backups as $backup) {
            $this->output->writeln('Importing ' . $backup);
            $bulk = new BulkWrite();
            foreach($this->processKillmails($backup) as $killmail) {
                $bulk->insert($killmail);
            }
            $this->killmails->collection->getManager()->executeBulkWrite($this->killmails->databaseName . '.' . $this->killmails->collectionName, $bulk);
        }
    }
}
