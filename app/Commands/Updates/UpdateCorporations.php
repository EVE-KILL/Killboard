<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\UpdateCorporation;
use EK\Models\Corporations;
use MongoDB\BSON\UTCDateTime;

class UpdateCorporations extends ConsoleCommand
{
    protected string $signature = 'update:corporations { --all }';
    protected string $description = 'Update the corporations in the database';

    public function __construct(
        protected Corporations $corporations,
        protected UpdateCorporation $updateCorporation
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $updated = ['updated' => ['$lt' => new UTCDateTime(strtotime('-7 days') * 1000)]];
        $corporationCount = $this->corporations->count($this->all ? [] : $updated);
        $this->out('Corporations to update: ' . $corporationCount);

        $progress = $this->progressBar($corporationCount);
        $corporationsToUpdate = [];

        foreach ($this->corporations->find($this->all ? [] : $updated) as $corporation) {
            $corporationsToUpdate[] = ['corporation_id' => $corporation['corporation_id']];
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
