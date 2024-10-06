<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Helpers\ESIData;
use EK\Jobs\UpdateAlliance;
use EK\Models\Alliances;
use MongoDB\BSON\UTCDateTime;

class UpdateAlliances extends ConsoleCommand
{
    protected string $signature = 'update:alliances { allianceId? : Process a single allianceId } { --all } { --updateHistory }';
    protected string $description = 'Update the alliances in the database';

    public function __construct(
        protected Alliances $alliances,
        protected UpdateAlliance $updateAlliance,
        protected ESIData $esiData
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        if ($this->allianceId) {
            $this->handleSingleAlliance();
        } else {
            $this->handleAllAlliances();
        }
    }

    /**
     * Handle updating a single alliance.
     */
    protected function handleSingleAlliance(): void
    {
        $allianceId = $this->allianceId;
        $updateHistory = $this->updateHistory ?? false;

        $this->out("Updating alliance with ID: {$allianceId}");
        $this->esiData->getAllianceInfo($allianceId, $updateHistory);
    }

    /**
     * Handle updating all alliances.
     */
    protected function handleAllAlliances(): void
    {
        $updatedCriteria = ['updated' => ['$lt' => new UTCDateTime(strtotime('-7 days') * 1000)]];
        $allianceCount = $this->alliances->count($this->all ? [] : $updatedCriteria);
        $this->out('Alliances to update: ' . $allianceCount);
        $updateHistory = $this->updateHistory ?? false;

        $progress = $this->progressBar($allianceCount);
        $alliancesToUpdate = [];

        foreach ($this->alliances->find($this->all ? [] : $updatedCriteria) as $alliance) {
            $alliancesToUpdate[] = ['alliance_id' => $alliance['alliance_id']];
            $progress->advance();
        }

        if (!empty($alliancesToUpdate)) {
            $this->updateAlliance->massEnqueue($alliancesToUpdate);
        }

        $progress->finish();
    }
}
