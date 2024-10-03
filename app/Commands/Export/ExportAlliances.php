<?php

namespace EK\Commands\Export;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Models\Alliances;
use Generator;
use MongoDB\BSON\UTCDateTime;
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
        $alliances = $this->alliances->collection->find([], ['projection' => ['_id' => 0, 'kills' => 0, 'losses' => 0, 'stats' => 0, 'last_modified' => 0, 'last_modified' => 0]]);

        // Export the alliances to the JSON file with inline timestamp cleanup
        $this->exportToJson($this->cleanupTimestampsInline($alliances), $path);

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

    private function exportToJson(Generator $alliances, string $path): void
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
