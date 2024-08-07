<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\ESI;
use EK\Logger\FileLogger;
use EK\Meilisearch\Meilisearch;
use Illuminate\Support\Collection;
use GuzzleHttp\Client;
use MongoDB\BSON\UTCDateTime;

class UpdateCharacter extends Jobs
{
    protected string $defaultQueue = "character";
    protected bool $requeue = false;

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
        protected \EK\Redis\Redis $redis,
        protected FileLogger $logger,
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $characterId = $data["character_id"];
        $deleted = false;

        $characterData = $this->characters->findOneOrNull([
            "character_id" => $characterId,
            "name" => ['$ne' => "Unknown"],
        ])?->toArray();
        if ($characterData === null) {
            $this->logger->info("Character $characterId not found in database, fetching from ESI");
            $characterData = $this->esiCharacters->getCharacterInfo($characterId);
        }

        if ($this->isCharacterDeleted($characterData)) {
            $this->logger->info("Character $characterId has been deleted, updating database and fetching from EVEWho");
            $this->updateDeletedCharacter($characterId);
            $characterData = $this->fetchCharacterDataFromEVEWho($characterId);
            if ($characterData) {
                $this->logger->info("Character $characterId found in EVEWho, updating database");
                $this->updateCharacterData($characterData, true);
            }
            return;
        }

        $this->updateCharacterData($characterData, $deleted);
    }

    protected function isCharacterDeleted(array $characterData): bool
    {
        $deleted = isset($characterData["error"]) && $characterData["error"] === "Character has been deleted!";
        if ($deleted) {
            $this->logger->info("Character {$characterData['character_id']} has been deleted");
        }
        return $deleted;
    }

    protected function updateDeletedCharacter(int $characterId): void
    {
        $this->characters->setData([
            "character_id" => $characterId,
            "deleted" => true,
        ]);
        $this->characters->save();
    }

    protected function fetchCharacterDataFromEVEWho(int $characterId): ?array
    {
        try {
            $client = new Client();
            $response = $client->get("https://evewho.com/api/character/{$characterId}");
            $data = json_decode($response->getBody()->getContents(), true);
            $characterInfo = $data["info"][0] ?? [];
            $characterHistory = $data["history"] ?? [];
            return [
                'character_id' => $characterInfo["character_id"] ?? $characterId,
                'name' => $characterInfo["name"] ?? "Unknown",
                'birthday' => new UTCDateTime(strtotime($characterInfo["birthday"]) * 1000),
                'corporation_id' => $characterInfo["corporation_id"] ?? 0,
                'corporation_name' => $this->fetchCorporationData($characterInfo["corporation_id"] ?? 0)["name"] ?? "",
                'alliance_id' => $characterInfo["alliance_id"] ?? 0,
                'alliance_name' => $this->fetchAllianceData($characterInfo["alliance_id"] ?? 0)["name"] ?? "",
                'security_status' => $characterInfo["sec_status"] ?? 0,
                'history' => array_map(function($history) {
                    return [
                        'corporation_id' => $history['corporation_id'],
                        'start_date' => new UTCDateTime(strtotime($history['start_date']) * 1000)
                    ];
                }, $characterHistory),
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to fetch data from EVEWho for character $characterId: " . $e->getMessage());
            return null;
        }
    }

    protected function updateCharacterData(array $characterData, bool $deleted): void
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

    protected function indexCharacterInSearch($characterData)
    {
        $this->meilisearch->addDocuments([
            "id" => $characterData["character_id"],
            "name" => $characterData["name"],
            "type" => "character",
        ]);
    }
}
