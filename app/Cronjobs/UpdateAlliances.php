<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\UpdateAlliance;
use EK\Logger\StdOutLogger;
use EK\Models\Alliances;
use MongoDB\BSON\UTCDateTime;

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

        $timeAgo = new UTCDateTime((time() - 7 * 86400) * 1000);
        // Find all alliances, and update them
        $staleAlliances = $this->alliances->find([
            'last_modified' => ['$lt' => $timeAgo],
        ], ['projection' => ['_id' => 0, 'alliance_id' => 1]]);

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
