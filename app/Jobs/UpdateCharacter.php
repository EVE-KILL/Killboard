<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\EveWho;
use EK\Meilisearch\Meilisearch;
use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;
use EK\Models\Characters;
use EK\Models\Alliances;
use EK\Models\Corporations;
use EK\Models\Factions;
use EK\ESI\Alliances as ESIAlliances;
use EK\ESI\Corporations as ESICorporations;
use EK\ESI\Characters as ESICharacters;
use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;

class UpdateCharacter extends Jobs
{
    protected string $defaultQueue = "character";
    public bool $requeue = false;

    public function __construct(
        protected Characters $characters,
        protected Alliances $alliances,
        protected Corporations $corporations,
        protected Factions $factions,
        protected ESIAlliances $esiAlliances,
        protected ESICorporations $esiCorporations,
        protected ESICharacters $esiCharacters,
        protected Meilisearch $meilisearch,
        protected Logger $logger,
        protected EveWho $eveWhoFetcher,
        protected RabbitMQ $rabbitMQ,
        protected UpdateMeilisearch $updateMeilisearch
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $characterId = $data["character_id"];
        if ($characterId === 0) {
            return;
        }

        $characterData = $this->characters->findOneOrNull([
            'character_id' => $characterId,
            'name' => ['$ne' => 'Unknown'],
        ])?->toArray();

        // If the character has been deleted, and is flagged as deleted, we just skip it
        if ($characterData && isset($characterData['deleted']) && $characterData['deleted'] === true) {
            return;
        }

        $lastUpdated = isset($characterData['last_updated']) ? $characterData['last_updated']?->toDateTime() ?? new \DateTime() : new \DateTime();
        if ($characterData === null || $lastUpdated < (new \DateTime())->modify('-7 day')) {
            $characterData = $this->esiCharacters->getCharacterInfo($characterId);
        }

        // If the character has been updated within the last 7 days, we can skip it
        if ($lastUpdated > (new \DateTime())->modify('-7 day')) {
            return;
        }


        if ($this->isCharacterDeleted($characterData)) {
            $this->characters->setData([
                "character_id" => $characterId,
                "deleted" => true,
            ]);
            $this->characters->save();
            return;
        }

        if ($this->isCharacterFound($characterData)) {
            $this->updateCharacterData($characterData);
        }
    }

    protected function isCharacterDeleted(array $characterData): bool
    {
        $deleted = isset($characterData["error"]) && $characterData["error"] === "Character has been deleted!";
        if ($deleted) {
            $this->logger->debug("Character {$characterData['character_id']} has been deleted", $characterData);
        }
        return $deleted;
    }

    protected function isCharacterFound(array $characterData): bool
    {
        $found = isset($characterData["error"]);
        if ($found) {
            $this->logger->debug("Character {$characterData['character_id']} not found", $characterData);
        }
        // Return the inverse because if $found is true, then the character is not found, meaning the return has to be inverted
        return !$found;
    }

    protected function updateDeletedCharacter(int $characterId): void
    {
        $this->characters->setData([
            "character_id" => $characterId,
            "deleted" => true,
        ]);
        $this->characters->save();
    }

    protected function updateCharacterData(array $characterData): void
    {
        $characterData = $characterData instanceof Collection ? $characterData->toArray() : $characterData;

        $allianceId = $characterData["alliance_id"] ?? 0;
        $corporationId = $characterData["corporation_id"] ?? 0;
        $factionId = $characterData["faction_id"] ?? 0;

        $allianceData = $this->fetchAllianceData($allianceId);
        $corporationData = $this->fetchCorporationData($corporationId);
        $factionData = $this->fetchFactionData($factionId);

        $characterData["alliance_name"] = $allianceData["name"] ?? "";
        $characterData["corporation_name"] = $corporationData["name"] ?? "";
        $characterData["faction_name"] = $factionData["name"] ?? "";
        $characterData["last_updated"] = new UTCDateTime(time() * 1000);
        $characterData['birthday'] = isset($characterData['birthday']) ? new UTCDateTime(strtotime($characterData['birthday']) * 1000) : null;

        ksort($characterData);

        $this->characters->setData($characterData);
        $this->characters->save();

        if (isset($characterData['name'])) {
            $this->updateMeilisearch->enqueue([
                "id" => $characterData["character_id"],
                "name" => $characterData["name"],
                "type" => "character",
            ]);
        }
    }

    protected function fetchAllianceData(int $allianceId): array
    {
        if ($allianceId > 0) {
            return $this->alliances->findOneOrNull([
                "alliance_id" => $allianceId,
            ])?->toArray() ?? $this->esiAlliances->getAllianceInfo($allianceId);
        }
        return [];
    }

    protected function fetchCorporationData(int $corporationId): array
    {
        if ($corporationId > 0) {
            return $this->corporations->findOneOrNull([
                "corporation_id" => $corporationId,
            ])?->toArray() ?? $this->esiCorporations->getCorporationInfo($corporationId);
        }
        return [];
    }

    protected function fetchFactionData($factionId)
    {
        if ($factionId > 0) {
            return $this->factions->findOne([
                "faction_id" => $factionId,
            ])?->toArray();
        }
        return [];
    }
}
