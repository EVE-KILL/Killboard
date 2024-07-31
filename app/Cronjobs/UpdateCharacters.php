<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\updateCharacter;
use EK\Logger\Logger;
use EK\Models\Characters;

class UpdateCharacters extends Cronjob
{
    protected string $cronTime = "0 * * * *";

    public function __construct(
        protected Characters $characters,
        protected updateCharacter $updateCharacter,
        protected Logger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        $this->logger->info("Updating characters with names set to Unknown");
        // Find characters with the name set to Unknown, but ignore them if they have deleted = true
        $unknownCharacters = $this->characters->find(
            [
                "name" => "Unknown",
                "deleted" => ['$ne' => true],
            ],
            ["limit" => 1000]
        );

        $updates = array_map(function ($character) {
            return [
                "character_id" => $character["character_id"],
            ];
        }, $unknownCharacters->toArray());

        $this->logger->info("Updating " . count($updates) . " characters");
        $this->updateCharacter->massEnqueue($updates);
    }
}
