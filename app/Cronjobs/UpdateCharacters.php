<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\UpdateCharacter;
use EK\Logger\StdOutLogger;
use EK\Models\Characters;
use MongoDB\BSON\UTCDateTime;

class UpdateCharacters extends Cronjob
{
    protected string $cronTime = "*/5 * * * *";

    public function __construct(
        protected Characters $characters,
        protected UpdateCharacter $updateCharacter,
        protected StdOutLogger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        return;
        $this->logger->info("Updating characters that haven't been updated in the last 30 days");

        $queueLength = $this->updateCharacter->queueLength();
        if ($queueLength > 0) {
            $this->logger->info("There are currently $queueLength characters in the queue, skipping this run");
            return;
        }

        $daysAgo = new UTCDateTime((time() - (30 * 86400)) * 1000);
        $characterCount = $this->characters->aproximateCount();
        // Get the amount to add every five minutes
        $limit = (int) round(((($characterCount / 30) / 24)/ 60) * 5, 0);

        // Find characters that haven't been updated in the last 14 days, but ignore them if they have deleted = true
        $staleCharacters = $this->characters->find(
            [
                "last_updated" => ['$lt' => $daysAgo],
                "deleted" => ['$ne' => true],
            ],
            [
                "limit" => $limit,
                "projection" => ["character_id" => 1, 'deleted' => 1, '_id' => 0],
            ]
        );

        $updates = [];
        foreach ($staleCharacters as $character) {
            if ($character['deleted'] || false === true) {
                continue;
            }

            $updates[] = ['character_id' => $character['character_id']];
        }

        $this->logger->info("Updating " . count($updates) . " characters");
        $this->updateCharacter->massEnqueue($updates);
    }
}
