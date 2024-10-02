<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Helpers\ESIData;
use EK\Jobs\UpdateCorporation;
use EK\Models\Corporations;
use MongoDB\BSON\UTCDateTime;

class UpdateCorporations extends ConsoleCommand
{
    protected string $signature = 'update:corporations { corporationId? : Process a single corporationId } { --all } { --forceUpdate } { --updateHistory }';
    protected string $description = 'Update the corporations in the database';

    public function __construct(
        protected Corporations $corporations,
        protected UpdateCorporation $updateCorporation,
        protected ESIData $esiData
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        if ($this->corporationId) {
            $this->handleSingleCorporation();
        } else {
            $this->handleAllCorporations();
        }
    }

    /**
     * Handle updating a single corporation.
     */
    protected function handleSingleCorporation(): void
    {
        $corporationId = $this->corporationId;
        $forceUpdate = $this->forceUpdate ?? false;
        $updateHistory = $this->updateHistory ?? false;

        $this->out("Updating corporation with ID: {$corporationId}");
        $this->esiData->getCorporationInfo($corporationId, $forceUpdate, $updateHistory);
    }

    /**
     * Handle updating all corporations.
     */
    protected function handleAllCorporations(): void
    {
        $updatedCriteria = ['updated' => ['$lt' => new UTCDateTime(strtotime('-7 days') * 1000)]];
        $corporationCount = $this->corporations->count($this->all ? [] : $updatedCriteria);
        $this->out('Corporations to update: ' . $corporationCount);
        $forceUpdate = $this->forceUpdate ?? false;
        $updateHistory = $this->updateHistory ?? false;
        $progress = $this->progressBar($corporationCount);
        $corporationsToUpdate = [];

        $cursor = $this->corporations->find(
            $this->all ? [] : $updatedCriteria,
            ['projection' => ['_id' => 0, 'corporation_id' => 1]]
        );

        foreach ($cursor as $corporation) {
            $corporationsToUpdate[] = [
                'corporation_id' => $corporation['corporation_id'],
                'force_update' => $forceUpdate,
                'update_history' => $updateHistory
            ];
            $progress->advance();

            // If we have collected 1000 corporations, enqueue them
            if (count($corporationsToUpdate) >= 1000) {
                $this->updateCorporation->massEnqueue($corporationsToUpdate);
                $corporationsToUpdate = []; // Reset the array
            }
        }

        // Enqueue any remaining corporations
        if (!empty($corporationsToUpdate)) {
            $this->updateCorporation->massEnqueue($corporationsToUpdate);
        }

        $progress->finish();
    }
}
