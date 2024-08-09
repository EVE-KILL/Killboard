<?php

namespace EK\Commands\Export;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Models\Characters;
use MongoDB\Driver\Cursor;
use SplFileObject;

class ExportCharacters extends ConsoleCommand
{
    public string $signature = 'export:characters { path : The path to export the characters to }';
    public string $description = 'Export all characters to a JSON file';

    public function __construct(
        protected Characters $characters,
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $this->out('Exporting characters..');
        $characterCount = $this->characters->count();
        $this->out('Total characters to export: ' . $characterCount);

        $path = $this->path;
        $this->checkAndCreatePath($path);

        // Use the MongoDB Cursor to get all characters
        $characters = $this->characters->collection->find([], ['projection' => ['_id' => 0, 'kills' => 0, 'losses' => 0, 'points' => 0, 'last_modified' => 0, 'last_updated' => 0]]);

        // Export the characters to the JSON file
        $this->exportToJson($characters, $path);

        $this->out('Export completed successfully.');
    }

    private function checkAndCreatePath(string &$path): void
    {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        if (is_dir($path)) {
            $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'characters.json';
        }
    }

    private function exportToJson(Cursor $characters, string $path): void
    {
        $file = new SplFileObject($path, 'w');
        $file->fwrite('[');

        $first = true;
        foreach ($characters as $character) {
            if (!$first) {
                $file->fwrite(',');
            }
            $file->fwrite(json_encode($character));
            $first = false;
        }

        $file->fwrite(']');
    }
}
