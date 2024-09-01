<?php

namespace EK\Commands\Export;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Models\Alliances;
use MongoDB\Driver\Cursor;
use SplFileObject;

class ExportAlliances extends ConsoleCommand
{
    public string $signature = 'export:alliances { path : The path to export the alliances to }';
    public string $description = 'Export all alliances to a JSON file';

    public function __construct(
        protected Alliances $alliances,
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $this->out('Exporting alliances..');
        $allianceCount = $this->alliances->aproximateCount();
        $this->out('Total alliances to export: ' . $allianceCount);

        $path = $this->path;
        $this->checkAndCreatePath($path);

        // Use the MongoDB Cursor to get all alliances
        $alliances = $this->alliances->collection->find([], ['projection' => ['_id' => 0, 'kills' => 0, 'losses' => 0, 'stats' => 0, 'last_modified' => 0, 'last_updated' => 0]]);

        // Export the alliances to the JSON file
        $this->exportToJson($alliances, $path);

        $this->out('Export completed successfully.');
    }

    private function checkAndCreatePath(string &$path): void
    {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        if (is_dir($path)) {
            $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'alliances.json';
        }
    }

    private function exportToJson(Cursor $alliances, string $path): void
    {
        $file = new SplFileObject($path, 'w');
        $file->fwrite('[');

        $first = true;
        foreach ($alliances as $alliance) {
            if (!$first) {
                $file->fwrite(',');
            }
            $file->fwrite(json_encode($alliance));
            $first = false;
        }

        $file->fwrite(']');
    }
}
