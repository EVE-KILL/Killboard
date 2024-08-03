<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\EveWho;
use EK\Meilisearch\Meilisearch;
use Illuminate\Support\Collection;

class UpdateCorporation extends Jobs
{
    protected string $defaultQueue = "corporation";

    public function __construct(
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Characters $characters,
        protected \EK\Models\Stations $stations,
        protected \EK\Models\Factions $factions,
        protected \EK\ESI\Alliances $esiAlliances,
        protected \EK\ESI\Corporations $esiCorporations,
        protected \EK\ESI\Characters $esiCharacters,
        protected \EK\ESI\Stations $esiStations,
        protected Meilisearch $meilisearch,
        protected EveWho $eveWhoFetcher,
        protected UpdateCharacter $updateCharacter,
        protected \EK\Redis\Redis $redis
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $corporationId = $data["corporation_id"];

        $corporationData = $this->fetchCorporationData($corporationId);
        $this->updateCorporationData($corporationData);
        $this->updateCorporationCharacters($corporationId);
    }

    protected function fetchCorporationData($corporationId)
    {
        return $this->corporations->findOneOrNull([
                "corporation_id" => $corporationId,
            ]) ?? $this->esiCorporations->getCorporationInfo($corporationId);
    }

    protected function updateCorporationData($corporationData)
    {
        $corporationData = $corporationData instanceof Collection ? $corporationData->toArray() : $corporationData;

        $corporationData["alliance_name"] = $this->fetchAllianceName($corporationData["alliance_id"] ?? 0);
        $corporationData["ceo_name"] = $this->fetchCharacterName($corporationData["ceo_id"] ?? 0);
        $corporationData["creator_name"] = $this->fetchCharacterName($corporationData["creator_id"] ?? 0);
        $corporationData["home_station_name"] = $this->fetchStationName($corporationData["home_station_id"] ?? 0);
        $corporationData["faction_name"] = $this->fetchFactionName($corporationData["faction_id"] ?? 0);

        ksort($corporationData);

        $this->corporations->setData($corporationData);
        $this->corporations->save();

        $this->indexCorporationInSearch($corporationData);
    }

    protected function fetchAllianceName($allianceId)
    {
        if ($allianceId > 0) {
            $allianceData = $this->alliances->findOneOrNull(["alliance_id" => $allianceId]) ??
                            $this->esiAlliances->getAllianceInfo($allianceId);
            return $allianceData["name"] ?? "";
        }
        return "";
    }

    protected function fetchCharacterName($characterId)
    {
        if ($characterId > 0) {
            $characterData = $this->characters->findOneOrNull(["character_id" => $characterId]) ??
                             $this->esiCharacters->getCharacterInfo($characterId);
            return $characterData["name"] ?? "";
        }
        return "";
    }

    protected function fetchStationName($stationId)
    {
        if ($stationId > 0) {
            $stationData = $this->stations->findOneOrNull(["station_id" => $stationId]) ??
                           $this->esiStations->getStationInfo($stationId);
            return $stationData["name"] ?? "";
        }
        return "";
    }

    protected function fetchFactionName($factionId)
    {
        if ($factionId > 0) {
            $factionData = $this->factions->findOne(["faction_id" => $factionId]);
            return $factionData["name"] ?? "";
        }
        return "";
    }

    protected function indexCorporationInSearch($corporationData)
    {
        $this->meilisearch->addDocuments([
            "id" => $corporationData["corporation_id"],
            "name" => $corporationData["name"],
            "ticker" => $corporationData["ticker"],
            "type" => "corporation",
        ]);
    }

    protected function updateCorporationCharacters($corporationId)
    {
        $url = "https://evewho.com/api/corplist/{$corporationId}";
        $request = $this->eveWhoFetcher->fetch($url);
        $data = $request["body"] ?? "";

        $decoded = json_validate($data) ? json_decode($data, true) : [];
        $characters = $decoded["characters"] ?? [];

        foreach ($characters as $character) {
            $this->characters->findOneOrNull([
                "character_id" => $character["character_id"],
            ]) ?? $this->updateCharacter->enqueue([
                "character_id" => $character["character_id"],
            ]);
        }
    }
}
