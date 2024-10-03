<?php

namespace EK\Commands\Export;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Models\Corporations;
use Generator;
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
        $corporations = $this->corporations->collection->find([], ['projection' => ['_id' => 0, 'kills' => 0, 'losses' => 0, 'points' => 0, 'last_modified' => 0, 'last_modified' => 0]]);

        // Export the corporations to the JSON file
        $this->exportToJson($this->cleanupTimestampsInline($corporations), $path);

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

    private function exportToJson(Generator $corporations, string $path): void
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

    private function cleanupTimestampsInline(Cursor $cursor): Generator
    {
        foreach ($cursor as $document) {
            yield $this->cleanupTimestamps($document);
        }
    }

    private function cleanupTimestamps(array|Generator $data): array
    {
        $returnData = [];

        foreach ($data as $key => $value) {
            $returnData[$key] = $value;
            // Check if the value is an instance of UTCDateTime
            if ($value instanceof UTCDateTime) {
                $returnData[$key] = $value->toDateTime()->getTimestamp();
            }

            // Check if the value is an array
            if (is_array($value)) {
                // If the array has the structure containing $date and $numberLong
                if (isset($value['$date']['$numberLong'])) {
                    $returnData[$key] = (new UTCDateTime($value['$date']['$numberLong']))->toDateTime()->getTimestamp();
                } else {
                    // Recursively process nested arrays
                    $returnData[$key] = $this->cleanupTimestamps($value);
                }
            }
        }

        return $returnData;
    }
}
