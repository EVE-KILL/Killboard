<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\UpdateAlliance;
use EK\Logger\StdOutLogger;
use EK\Models\Alliances;

class UpdateAlliances extends Cronjob
{
    protected string $cronTime = "0 0 * * *";

    public function __construct(
        protected Alliances $alliances,
        protected UpdateAlliance $updateAlliance,
        protected StdOutLogger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        $this->logger->info("Updating alliances");

        // Find alliances that haven't been updated in the last 14 days
        $staleAlliances = $this->alliances->find([]);

        $updates = [];
        foreach($staleAlliances as $alliance) {
            $updates[] = [
                'alliance_id' => $alliance['alliance_id'],
            ];
        }

        $this->logger->info("Updating " . count($updates) . " alliances");
        $this->updateAlliance->massEnqueue($updates);
    }
}
