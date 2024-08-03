<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\UpdateAlliance;
use EK\Models\Alliances;
use MongoDB\BSON\UTCDateTime;

class UpdateAlliances extends Cronjob
{
    protected string $cronTime = "0 * * * *";

    public function __construct(
        protected Alliances $alliances,
        protected UpdateAlliance $updateAlliance
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->logger->info("Updating alliances that haven't been updated in the last 14 days");

        $fourteenDaysAgo = new UTCDateTime((time() - 14 * 86400) * 1000);

        // Find alliances that haven't been updated in the last 14 days
        $staleAlliances = $this->alliances->find(
            [
                "last_modified" => ['$lt' => $fourteenDaysAgo],
            ],
            ["limit" => 100]
        );

        $updates = array_map(function ($alliance) {
            return [
                "alliance_id" => $alliance["alliance_id"],
            ];
        }, $staleAlliances->toArray());

        $this->logger->info("Updating " . count($updates) . " alliances");
        $this->updateAlliance->massEnqueue($updates);
    }
}
