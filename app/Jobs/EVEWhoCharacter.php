<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\EveWho;
use EK\Logger\Logger;
use EK\Models\Characters;
use EK\RabbitMQ\RabbitMQ;
use MongoDB\BSON\UTCDateTime;

class EVEWhoCharacter extends Jobs
{
    protected string $defaultQueue = 'evewho';
    public function __construct(
        protected EveWho $eveWhoFetcher,
        protected Characters $characters,
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
    ) {
        parent::__construct($rabbitMQ, $logger);
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
                "last_modified" => new UTCDateTime()
            ];

            // Birthday can be extracted from history, if history exists - as the element with the lowest start date (Which is in the format: YYYY/MM/DD HH:ii - example: 2006/12/07 21:48)
            // If no history exists, don't set a birthday field
            if (isset($data["history"]) && count($data["history"]) > 0) {
                $birthday = $data["history"][0]["start"];
                $characterData["birthday"] = new UTCDateTime(strtotime($birthday) * 1000);
            }


            $this->characters->setData($characterData);
            $this->characters->save();

            $this->logger->debug("Updated character $characterId from EVEWho");
        } catch (\Exception $e) {
            $this->logger->debug("Failed to fetch data from EVEWho for character $characterId: " . $e->getMessage());
        }
    }
}
