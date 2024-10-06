<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Helpers\ESIData;
use EK\Jobs\EntityHistoryUpdate;
use EK\Jobs\UpdateCharacter;
use EK\Models\Characters;
use MongoDB\BSON\UTCDateTime;

class UpdateCharacters extends ConsoleCommand
{
    protected string $signature = 'update:characters { characterId? : Process a single characterId } { --all } { --updateHistory }';
    protected string $description = 'Update the characters in the database (Default 30 days)';

    public function __construct(
        protected Characters $characters,
        protected UpdateCharacter $updateCharacter,
        protected EntityHistoryUpdate $entityHistoryUpdate,
        protected ESIData $esiData
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        if ($this->characterId) {
            $this->handleSingleCharacter();
        } else {
            $this->handleAllCharacters();
        }
    }

    /**
     * Handle updating a single character.
     */
    protected function handleSingleCharacter(): void
    {
        $characterId = (int) $this->characterId;
        $updateHistory = $this->updateHistory ?? false;

        $this->out("Updating character with ID: {$characterId}");
        $this->esiData->getCharacterInfo($characterId, $updateHistory);
    }

    /**
     * Handle updating all characters.
     */
    protected function handleAllCharacters(): void
    {
        $updatedCriteria = ['last_modified' => ['$lt' => new UTCDateTime(strtotime('-30 days') * 1000)]];
        $characterCount = $this->characters->count($this->all ? [] : $updatedCriteria);
        $this->out('Characters to update: ' . $characterCount);

        $progress = $this->progressBar($characterCount);
        $charactersToUpdate = [];
        $charactersToUpdateHistory = [];

        $cursor = $this->characters->collection->find(
            $this->all ? [] : $updatedCriteria,
            ['projection' => ['_id' => 0, 'character_id' => 1]]
        );

        foreach ($cursor as $character) {
            $charactersToUpdate[] = [
                'character_id' => $character['character_id']
            ];

            if ($this->updateHistory) {
                $charactersToUpdateHistory[] = [
                    'entity_id' => $character['character_id'],
                    'entity_type' => 'character'
                ];
            }

            $progress->advance();

            // If we have collected 1000 characters, enqueue them
            if (count($charactersToUpdate) >= 1000) {
                $this->updateCharacter->massEnqueue($charactersToUpdate);
                $charactersToUpdate = []; // Reset the array
            }


            if (count($charactersToUpdateHistory) >= 1000) {
                $this->entityHistoryUpdate->massEnqueue($charactersToUpdateHistory);
                $charactersToUpdateHistory = []; // Reset the array
            }

        }

        // Enqueue any remaining characters
        if (!empty($charactersToUpdate)) {
            $this->updateCharacter->massEnqueue($charactersToUpdate);
        }

        if (!empty($charactersToUpdateHistory)) {
            $this->entityHistoryUpdate->massEnqueue($charactersToUpdateHistory);
        }

        $progress->finish();
    }
}
