<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\ESI;
use EK\Meilisearch\Meilisearch;
use Illuminate\Support\Collection;
use GuzzleHttp\Client;

class UpdateCharacter extends Jobs
{
    protected string $defaultQueue = "character";

    public function __construct(
        protected \EK\Models\Characters $characters,
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Factions $factions,
        protected \EK\ESI\Alliances $esiAlliances,
        protected \EK\ESI\Corporations $esiCorporations,
        protected \EK\ESI\Characters $esiCharacters,
        protected ESI $esiFetcher,
        protected Meilisearch $meilisearch,
        protected \EK\Redis\Redis $redis
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $characterId = $data["character_id"];
        $deleted = false;

        $characterData = $this->fetchCharacterData($characterId);

        if ($this->isCharacterDeleted($characterData)) {
            $this->updateDeletedCharacter($characterId);
            $characterData = $this->fetchCharacterDataFromEVEWho($characterId);
            if ($characterData) {
                $this->updateCharacterData($characterData, true);
            }
            return;
        }

        $this->updateCharacterData($characterData, $deleted);
    }

    protected function fetchCharacterData($characterId)
    {
        return $this->characters->findOneOrNull([
                "character_id" => $characterId,
                "name" => ['$ne' => "Unknown"],
            ]) ?? $this->esiCharacters->getCharacterInfo($characterId);
    }

    protected function isCharacterDeleted($characterData)
    {
        return isset($characterData["error"]) && $characterData["error"] === "Character has been deleted!";
    }

    protected function updateDeletedCharacter($characterId)
    {
        $this->characters->setData([
            "character_id" => $characterId,
            "deleted" => true,
        ]);
        $this->characters->save();
    }

    protected function fetchCharacterDataFromEVEWho($characterId)
    {
        try {
            $client = new Client();
            $response = $client->get("https://evewho.com/api/character/{$characterId}");
            $data = json_decode($response->getBody()->getContents(), true);
            return [
                "character_id" => $characterId,
                "name" => $data["name"] ?? "Unknown",
                "alliance_id" => $data["alliance_id"] ?? 0,
                "corporation_id" => $data["corporation_id"] ?? 0,
                "faction_id" => $data["faction_id"] ?? 0,
                "deleted" => true, // Ensure the deleted flag is set
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to fetch data from EVEWho for character $characterId: " . $e->getMessage());
            return null;
        }
    }

    protected function updateCharacterData($characterData, $deleted)
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

        ksort($characterData);

        $this->characters->setData($characterData);
        $this->characters->save();

        if ($deleted === false) {
            $this->indexCharacterInSearch($characterData);
        }
    }

    protected function fetchAllianceData($allianceId)
    {
        if ($allianceId > 0) {
            return $this->alliances->findOneOrNull([
                "alliance_id" => $allianceId,
            ]) ?? $this->esiAlliances->getAllianceInfo($allianceId);
        }
        return [];
    }

    protected function fetchCorporationData($corporationId)
    {
        if ($corporationId > 0) {
            return $this->corporations->findOneOrNull([
                "corporation_id" => $corporationId,
            ]) ?? $this->esiCorporations->getCorporationInfo($corporationId);
        }
        return [];
    }

    protected function fetchFactionData($factionId)
    {
        if ($factionId > 0) {
            return $this->factions->findOne([
                "faction_id" => $factionId,
            ]);
        }
        return [];
    }

    protected function indexCharacterInSearch($characterData)
    {
        $this->meilisearch->addDocuments([
            "id" => $characterData["character_id"],
            "name" => $characterData["name"],
            "type" => "character",
        ]);
    }
}
