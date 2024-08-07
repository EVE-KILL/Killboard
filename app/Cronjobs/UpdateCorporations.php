<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\UpdateCorporation;
use EK\Models\Corporations;
use MongoDB\BSON\UTCDateTime;

class UpdateCorporations extends Cronjob
{
    protected string $cronTime = "0 * * * *";

    public function __construct(
        protected Corporations $corporations,
        protected UpdateCorporation $updateCorporation
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->logger->info("Updating corporations that haven't been updated in the last 14 days");

        $fourteenDaysAgo = new UTCDateTime((time() - 14 * 86400) * 1000);

        // Find corporations that haven't been updated in the last 14 days
        $staleCorporations = $this->corporations->find(
            [
                "last_modified" => ['$lt' => $fourteenDaysAgo],
            ],
            ["limit" => 2000]
        );

        $updates = array_map(function ($corporation) {
            return [
                "corporation_id" => $corporation["corporation_id"],
            ];
        }, $staleCorporations->toArray());

        $this->logger->info("Updating " . count($updates) . " corporations");
        $this->updateCorporation->massEnqueue($updates);
    }
}
