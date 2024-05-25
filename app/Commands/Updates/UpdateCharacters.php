<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Models\Characters;

class UpdateCharacters extends ConsoleCommand
{
    protected string $signature = 'update:characters { --all }';
    protected string $description = 'Update the characters in the database';

    public function __construct(
        protected Characters $characters,
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        //$updated = ['updated' => ['$lt' => new \MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000)]];
        //$characterCount = $this->characters->count($this->all ? [] : $updated);
        //$this->out('Characters to update: ' . $characterCount);
        //$progress = $this->progressBar($characterCount);
        //foreach ($this->characters->find($this->all ? [] : $updated) as $character) {
            //$this->charactersQueue->enqueue(['characterID' => $character['characterID']]);
            //$progress->advance();
        //}

        //$progress->finish();
    }
}