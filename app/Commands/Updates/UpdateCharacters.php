<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\UpdateCharacter;
use EK\Models\Characters;
use MongoDB\BSON\UTCDateTime;

class UpdateCharacters extends ConsoleCommand
{
    protected string $signature = 'update:characters { --all } { --forceUpdate } { --updateHistory }';
    protected string $description = 'Update the characters in the database (Default 30 days)';

    public function __construct(
        protected Characters $characters,
        protected UpdateCharacter $updateCharacter
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $updated = ['last_updated' => ['$lt' => new UTCDateTime(strtotime('-30 days') * 1000)]];
        $characterCount = $this->characters->count($this->all ? [] : $updated);
        $this->out('Characters to update: ' . $characterCount);
        $progress = $this->progressBar($characterCount);
        $forceUpdate = $this->forceUpdate ?? false;
        $updateHistory = $this->updateHistory ?? false;

        $charactersToUpdate = [];
        $cursor = $this->characters->collection->find(
            $this->all ? [] : $updated,
            ['projection' => ['_id' => 0, 'character_id' => 1]]
        );

        foreach ($cursor as $character) {
            $charactersToUpdate[] = ['character_id' => $character['character_id']];
            $progress->advance();

            // If we have collected 1000 characters, enqueue them
            if (count($charactersToUpdate) >= 1000) {
                $this->updateCharacter->massEnqueue($charactersToUpdate, $forceUpdate, $updateHistory);
                $charactersToUpdate = []; // Reset the array
            }
        }

        // Enqueue any remaining characters
        if (!empty($charactersToUpdate)) {
            $this->updateCharacter->massEnqueue($charactersToUpdate, $forceUpdate, $updateHistory);
        }

        $progress->finish();
    }
}
