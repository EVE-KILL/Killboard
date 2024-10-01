<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\UpdateAlliance;
use EK\Models\Alliances;
use MongoDB\BSON\UTCDateTime;

class UpdateAlliances extends ConsoleCommand
{
    protected string $signature = 'update:alliances { --all } { --forceUpdate }';
    protected string $description = 'Update the alliances in the database';

    public function __construct(
        protected Alliances $alliances,
        protected UpdateAlliance $updateAlliance
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $updated = ['updated' => ['$lt' => new UTCDateTime(strtotime('-7 days') * 1000)]];
        $allianceCount = $this->alliances->count($this->all ? [] : $updated);
        $this->out('Alliances to update: ' . $allianceCount);
        $forceUpdate = $this->forceUpdate ?? false;
        $updateHistory = $this->updateHistory ?? false;

        $progress = $this->progressBar($allianceCount);
        $alliancesToUpdate = [];

        foreach ($this->alliances->find($this->all ? [] : $updated) as $alliance) {
            $alliancesToUpdate[] = ['alliance_id' => $alliance['alliance_id'], 'force_update' => $forceUpdate];
            $progress->advance();
        }

        if (!empty($alliancesToUpdate)) {
            $this->updateAlliance->massEnqueue($alliancesToUpdate);
        }

        $progress->finish();
    }
}
