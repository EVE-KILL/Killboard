<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\UpdateCharacter;
use EK\Models\Characters;

class UpdateCharacters extends ConsoleCommand
{
    protected string $signature = 'update:characters { --all }';
    protected string $description = 'Update the characters in the database';

    public function __construct(
        protected Characters $characters,
        protected UpdateCharacter $updateCharacter
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $updated = ['updated' => ['$lt' => new \MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000)]];
        $characterCount = $this->characters->count($this->all ? [] : $updated);
        $this->out('Characters to update: ' . $characterCount);
        $progress = $this->progressBar($characterCount);

        $cursor = $this->characters->collection->find($this->all ? [] : $updated, ['projection' => ['_id' => 0, 'character_id' => 1]]);
        foreach ($cursor as $character) {
            $this->updateCharacter->enqueue(['character_id' => $character['character_id']]);
            $progress->advance();
        }

        $progress->finish();
    }
}
