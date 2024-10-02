<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\UpdateCorporation;
use EK\Logger\StdOutLogger;
use EK\Models\Corporations;
use MongoDB\BSON\UTCDateTime;

class UpdateCorporations extends Cronjob
{
    protected string $cronTime = "0 * * * *";

    public function __construct(
        protected Corporations $corporations,
        protected UpdateCorporation $updateCorporation,
        protected StdOutLogger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        $this->logger->info("Updating corporations that haven't been updated in the last 7 days");

        $sevenDaysAgo = new UTCDateTime((time() - 7 * 86400) * 1000);

        // Find corporations that haven't been updated in the last 14 days
        $staleCorporations = $this->corporations->find(
            [
                "last_modified" => ['$lt' => $sevenDaysAgo],
            ],
            ["limit" => 2000]
        );

        $updates = [];
        foreach ($staleCorporations as $corporation) {
            $updates[] = [
                "corporation_id" => $corporation["corporation_id"],
            ];
        }

        $this->logger->info("Updating " . count($updates) . " corporations");
        $this->updateCorporation->massEnqueue($updates);
    }
}
