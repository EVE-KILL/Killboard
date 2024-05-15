<?php

namespace EK\Commands\Updates;

use EK\Api\ConsoleCommand;
use EK\Models\Alliances;

class UpdateAlliances extends ConsoleCommand
{
    protected string $signature = 'update:alliances { --all }';
    protected string $description = 'Update the alliances in the database';

    public function __construct(
        protected Alliances $alliances
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        //$updated = ['updated' => ['$lt' => new \MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000)]];
        //$allianceCount = $this->alliances->count($this->all ? [] : $updated);
        //$this->out('Alliances to update: ' . $allianceCount);
        //$progress = $this->progressBar($allianceCount);
        //foreach ($this->alliances->find($this->all ? [] : $updated) as $alliance) {
        //    $this->alliancesQueue->enqueue(['allianceID' => $alliance['allianceID']]);
        //    $progress->advance();
        //}

        //$progress->finish();
    }
}
