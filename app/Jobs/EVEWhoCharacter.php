<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\EveWho;
use EK\Models\Characters;
use EK\RabbitMQ\RabbitMQ;

class EVEWhoCharacter extends Jobs
{
    protected string $defaultQueue = 'evewho';
    public function __construct(
        protected EveWho $eveWhoFetcher,
        protected Characters $characters,
        protected RabbitMQ $rabbitMQ,
    ) {
        parent::__construct($rabbitMQ);
    }

    public function handle(array $data): void
    {
        $characterId = $data['character_id'];

        try {
            $response = $this->eveWhoFetcher->fetch("https://evewho.com/api/character/{$characterId}");
            $data = json_decode($response['body'], true);

            $characterData = [
                "character_id" => $characterId,
                "name" => $data["name"] ?? "Unknown",
                "alliance_id" => $data["alliance_id"] ?? 0,
                "corporation_id" => $data["corporation_id"] ?? 0,
                "faction_id" => $data["faction_id"] ?? 0,
                "last_modified" => new \MongoDB\BSON\UTCDateTime()
            ];

            $this->characters->setData($characterData);
            $this->characters->save();

            $this->logger->info("Updated character $characterId from EVEWho");
        } catch (\Exception $e) {
            $this->logger->error("Failed to fetch data from EVEWho for character $characterId: " . $e->getMessage());
        }
    }
}
