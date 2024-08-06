<?php

namespace EK\Commands\Scrape;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\CharacterScrape;
use EK\Models\Characters;

/**
 * @property $manualPath
 */
class ScrapeCharacters extends ConsoleCommand
{
    protected string $signature = 'scrape:characters';
    protected string $description = 'This loops through all the characters and finds characterId holes in the database, and fetches the missing characters.';

    public function __construct(
        protected Characters $characters,
        protected CharacterScrape $characterScrape
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $this->characterScrape->emptyQueue();
        $chunkSize = 10000; // Variable for chunk size

        // The actual lowest character id known is 90000001
        $lowestCharacterId = 90000001;
        //$this->characters->findOne([
        //    'character_id' => ['$gt' => 3999999],
        //], [
        //    'sort' => ['character_id' => 1],
        //    'limit' => -1,
        //])->toArray()['character_id'];

        $highestCharacterId = $this->characters->findOne([], [
            'sort' => ['character_id' => -1],
            'limit' => 1,
        ])->toArray()['character_id'];

        $this->out("Lowest character ID: $lowestCharacterId");
        $this->out("Highest character ID: $highestCharacterId");
        $this->out("Starting to scrape characters...");

        $missingCharacterIds = [];
        $currentId = $lowestCharacterId;
        $characterIdsEnqueued = 0;

        while ($currentId <= $highestCharacterId) {
            $nextChunkEnd = $currentId + $chunkSize - 1;

            $existingIds = $this->characters->find([
                'character_id' => ['$gte' => $currentId, '$lte' => $nextChunkEnd]
            ], [
                'projection' => ['character_id' => 1]
            ])->toArray();

            $existingIds = array_column($existingIds, 'character_id');
            $allIdsInRange = range($currentId, $nextChunkEnd);
            $missingInChunk = array_diff($allIdsInRange, $existingIds);

            if (!empty($missingInChunk)) {
                $missingCharacterIds = array_merge($missingCharacterIds, $missingInChunk);
            }

            $currentId = $nextChunkEnd + 1;

            if (count($missingCharacterIds) >= $chunkSize) {
                $this->massEnqueueMissingCharacters($missingCharacterIds, $characterIdsEnqueued);
                $missingCharacterIds = []; // Reset the array after enqueueing
            }

            $characterIdsEnqueued += count($missingInChunk);
        }

        // Enqueue any remaining missing character IDs that didn't fill up a whole chunk
        if (!empty($missingCharacterIds)) {
            $this->massEnqueueMissingCharacters($missingCharacterIds, $characterIdsEnqueued);
        }

        $characterIdsEnqueued += count($missingCharacterIds);
        $this->out("Character scraping complete.");
        $this->out("Enqueued $characterIdsEnqueued characters.");
    }

    private function massEnqueueMissingCharacters(array $missingCharacterIds, int $characterIdsEnqueued): void
    {
        $this->out("Enqueueing " . count($missingCharacterIds) . " missing characters. Total enqueued: $characterIdsEnqueued");
        $queueData = array_map(fn($id) => ['character_id' => $id], $missingCharacterIds);
        $this->characterScrape->massEnqueue($queueData);
    }
}
