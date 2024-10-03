<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\UpdateCharacter;
use EK\Logger\StdOutLogger;
use EK\Models\Characters;
use MongoDB\BSON\UTCDateTime;

class UpdateCharacters extends Cronjob
{
    protected string $cronTime = "0 * * * *";

    public function __construct(
        protected Characters $characters,
        protected UpdateCharacter $updateCharacter,
        protected StdOutLogger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        $this->logger->info("Updating characters that haven't been updated in the last 30 days");
        $timeAgo = new UTCDateTime((time() - 30 * 86400) * 1000);

        // Find up to 1 million characters that haven't been updated in the last 30 days
        $staleCharacters = $this->characters->find([
            'last_modified' => ['$lt' => $timeAgo],
        ], ['limit' => 1000000, 'projection' => ['_id' => 0, 'character_id' => 1]]);

        $updates = [];
        foreach ($staleCharacters as $character) {
            $updates[] = [
                'character_id' => $character['character_id']
            ];
        }

        $this->logger->info("Updating " . count($updates) . " characters");
        $this->updateCharacter->massEnqueue($updates);
    }
}
