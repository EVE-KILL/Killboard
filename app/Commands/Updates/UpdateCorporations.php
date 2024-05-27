<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\updateCorporation;
use EK\Models\Corporations;

class UpdateCorporations extends ConsoleCommand
{
    protected string $signature = 'update:corporations { --all }';
    protected string $description = 'Update the corporations in the database';

    public function __construct(
        protected Corporations $corporations,
        protected updateCorporation $updateCorporation
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $updated = ['updated' => ['$lt' => new \MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000)]];
        $corporationCount = $this->corporations->count($this->all ? [] : $updated);
        $this->out('Corporations to update: ' . $corporationCount);
        $progress = $this->progressBar($corporationCount);
        foreach ($this->corporations->find($this->all ? [] : $updated) as $corporation) {
            $this->updateCorporation->enqueue(['corporation_id' => $corporation['corporation_id']]);
            $progress->advance();
        }

        $progress->finish();
    }
}
