<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\EveWho;
use EK\Logger\FileLogger;
use EK\Meilisearch\Meilisearch;
use EK\Redis\Redis;
use Illuminate\Support\Collection;

class UpdateAlliance extends Jobs
{
    protected string $defaultQueue = "alliance";

    public function __construct(
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Characters $characters,
        protected \EK\Models\Factions $factions,
        protected \EK\ESI\Alliances $esiAlliances,
        protected \EK\ESI\Corporations $esiCorporations,
        protected \EK\ESI\Characters $esiCharacters,
        protected Meilisearch $meilisearch,
        protected EveWho $eveWhoFetcher,
        protected UpdateCharacter $updateCharacter,
        protected Redis $redis,
        protected FileLogger $logger,
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $allianceId = $data["alliance_id"];

        $allianceData = $this->fetchAllianceData($allianceId);

        $this->updateAllianceData($allianceData);
        $this->updateAllianceCharacters($allianceId);
    }

    protected function fetchAllianceData($allianceId)
    {
        return $this->alliances->findOneOrNull(["alliance_id" => $allianceId]) ??
               $this->esiAlliances->getAllianceInfo($allianceId);
    }

    protected function updateAllianceData($allianceData)
    {
        $allianceData = $allianceData instanceof Collection ? $allianceData->toArray() : $allianceData;

        $allianceData["creator_corporation_name"] = $this->fetchCorporationName($allianceData["creator_corporation_id"]);
        $allianceData["executor_corporation_name"] = $this->fetchCorporationName($allianceData["executor_corporation_id"]);
        $allianceData["creator_name"] = $this->fetchCharacterName($allianceData["creator_id"]);
        $allianceData["faction_name"] = $this->fetchFactionName($allianceData["faction_id"] ?? 0);

        ksort($allianceData);

        $this->alliances->setData($allianceData);
        $this->alliances->save();

        $this->indexAllianceInSearch($allianceData);
    }

    protected function fetchCorporationName($corporationId)
    {
        if ($corporationId > 0) {
            $corporationData = $this->corporations->findOneOrNull(["corporation_id" => $corporationId]) ??
                               $this->esiCorporations->getCorporationInfo($corporationId);
            return $corporationData["name"] ?? "";
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

    protected function fetchFactionName($factionId)
    {
        if ($factionId > 0) {
            $factionData = $this->factions->findOne(["faction_id" => $factionId]);
            return $factionData["name"] ?? "";
        }
        return "";
    }

    protected function indexAllianceInSearch($allianceData)
    {
        $this->meilisearch->addDocuments([
            "id" => $allianceData["alliance_id"],
            "name" => $allianceData["name"],
            "ticker" => $allianceData["ticker"],
            "type" => "alliance",
        ]);
    }

    protected function updateAllianceCharacters($allianceId)
    {
        $url = "https://evewho.com/api/allilist/{$allianceId}";
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
