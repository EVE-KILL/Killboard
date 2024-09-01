<?php

namespace EK\Commands\Export;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Models\Corporations;
use MongoDB\Driver\Cursor;
use SplFileObject;

class ExportCorporations extends ConsoleCommand
{
    public string $signature = 'export:corporations { path : The path to export the corporations to }';
    public string $description = 'Export all corporations to a JSON file';

    public function __construct(
        protected Corporations $corporations,
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $this->out('Exporting corporations..');
        $corporationCount = $this->corporations->aproximateCount();
        $this->out('Total corporations to export: ' . $corporationCount);

        $path = $this->path;
        $this->checkAndCreatePath($path);

        // Use the MongoDB Cursor to get all corporations
        $corporations = $this->corporations->collection->find([], ['projection' => ['_id' => 0, 'kills' => 0, 'losses' => 0, 'points' => 0, 'last_modified' => 0, 'last_updated' => 0]]);

        // Export the corporations to the JSON file
        $this->exportToJson($corporations, $path);

        $this->out('Export completed successfully.');
    }

    private function checkAndCreatePath(string &$path): void
    {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        if (is_dir($path)) {
            $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'corporations.json';
        }
    }

    private function exportToJson(Cursor $corporations, string $path): void
    {
        $file = new SplFileObject($path, 'w');
        $file->fwrite('[');

        $first = true;
        foreach ($corporations as $corporation) {
            if (!$first) {
                $file->fwrite(',');
            }
            $file->fwrite(json_encode($corporation));
            $first = false;
        }

        $file->fwrite(']');
    }
}
