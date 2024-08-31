<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\CharacterScrape as FetchersCharacterScrape;
use EK\Meilisearch\Meilisearch;
use EK\Models\Characters;
use EK\Models\Alliances;
use EK\Models\Corporations;
use EK\Models\Factions;
use EK\ESI\Alliances as ESIAlliances;
use EK\ESI\Corporations as ESICorporations;
use EK\ESI\Characters as ESICharacters;
use EK\Fetchers\EveWho;
use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;
use EK\Webhooks\Webhooks;
use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;

class CharacterScrape extends Jobs
{
    protected string $defaultQueue = 'character_scrape';
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
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
        protected FetchersCharacterScrape $esiFetcher,
        protected EveWho $eveWhoFetcher,
        protected Webhooks $webhooks
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $characterId = $data["character_id"];

        $existingCharacter = $this->characters->findOneOrNull([
            "character_id" => $characterId,
        ], [], 0)?->toArray();

        if ($existingCharacter !== null) {
            return;
        }

        $characterData = $this->fetchCharacter($characterId);

        if ($this->isError($characterData) === false) {
            $this->updateCharacterData($characterData);
        }
    }

    protected function isError(array $characterData): bool
    {
        return isset($characterData["error"]);
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
        $characterData['last_updated'] = new UTCDateTime(time() * 1000);
        $characterData['birthday'] = new UTCDateTime(strtotime($characterData['birthday']) * 1000);

        ksort($characterData);

        $this->characters->setData($characterData);
        $this->characters->save();

        // We found a new character, let the webhooks know
        $this->webhooks->sendToNewCharactersFound("{$characterData['name']} / {$characterData['corporation_name']} | <https://eve-kill.com/character/{$characterData['character_id']}>");
        $this->indexCharacterInSearch($characterData);
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

    protected function fetchCharacter(int $characterId): array
    {
        if ($characterId < 10000) {
            return [];
        }

        $characterData = $this->esiFetcher->fetch('/latest/characters/' . $characterId);
        $characterData = json_validate($characterData['body']) ? json_decode($characterData['body'], true) : [];
        $characterData['character_id'] = $characterId;

        ksort($characterData);
        return $characterData;
    }
}
