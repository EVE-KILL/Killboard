<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\UpdateAlliance;
use EK\Models\Alliances;
use MongoDB\BSON\UTCDateTime;

class UpdateAlliances extends Cronjob
{
    protected string $cronTime = "0 0 * * *";

    public function __construct(
        protected Alliances $alliances,
        protected UpdateAlliance $updateAlliance
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->logger->info("Updating alliances");

        // Find alliances that haven't been updated in the last 14 days
        $staleAlliances = $this->alliances->find([]);

        $updates = array_map(function ($alliance) {
            return [
                "alliance_id" => $alliance["alliance_id"],
            ];
        }, $staleAlliances->toArray());

        $this->logger->info("Updating " . count($updates) . " alliances");
        $this->updateAlliance->massEnqueue($updates);
    }
}
