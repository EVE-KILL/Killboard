<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\UpdateCharacter;
use EK\Models\Characters;
use MongoDB\BSON\UTCDateTime;

class UpdateCharacters extends Cronjob
{
    protected string $cronTime = "0 * * * *";

    public function __construct(
        protected Characters $characters,
        protected UpdateCharacter $updateCharacter
    ) {
    }

    public function handle(): void
    {
        $this->logger->info("Updating characters that haven't been updated in the last 14 days");

        $fourteenDaysAgo = new UTCDateTime((time() - 14 * 86400) * 1000);

        // Find characters that haven't been updated in the last 14 days, but ignore them if they have deleted = true
        $staleCharacters = $this->characters->find(
            [
                "last_modified" => ['$lt' => $fourteenDaysAgo],
                "deleted" => ['$ne' => true],
            ],
            ["limit" => 15000]
        );

        $updates = array_map(function ($character) {
            return [
                "character_id" => $character["character_id"],
            ];
        }, $staleCharacters->toArray());

        $this->logger->info("Updating " . count($updates) . " characters");

        foreach ($updates as $update) {
            $this->updateCharacter->enqueue(['character_id' => $update["character_id"]]);
        }
    }
}
