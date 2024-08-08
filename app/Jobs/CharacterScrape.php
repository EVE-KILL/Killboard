<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\CharacterScrape as FetchersCharacterScrape;
use EK\Logger\FileLogger;
use EK\Meilisearch\Meilisearch;
use EK\Redis\Redis;
use EK\Models\Characters;
use EK\Models\Alliances;
use EK\Models\Corporations;
use EK\Models\Factions;
use EK\ESI\Alliances as ESIAlliances;
use EK\ESI\Corporations as ESICorporations;
use EK\ESI\Characters as ESICharacters;
use EK\Fetchers\EveWho;
use EK\Webhooks\Webhooks;
use Illuminate\Support\Collection;

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
        protected Redis $redis,
        protected FileLogger $logger,
        protected FetchersCharacterScrape $esiFetcher,
        protected EveWho $eveWhoFetcher,
        protected Webhooks $webhooks,
        protected EmitCharacterWS $emitCharacterWS
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $characterId = $data["character_id"];
        $deleted = false;

        $existingCharacter = $this->characters->findOneOrNull([
            "character_id" => $characterId,
        ], [], 0)?->toArray();

        if ($existingCharacter !== null) {
            return;
        }

        $characterData = $this->fetchCharacter($characterId);

        if ($this->isCharacterFound($characterData)) {
            $this->updateCharacterData($characterData, $deleted);
        }
    }

    protected function isCharacterFound(array $characterData): bool
    {
        $found = isset($characterData["error"]);
        if ($found) {
            $this->logger->info("Character {$characterData['character_id']} not found");
        }
        // Return the inverse because if $found is true, then the character is not found, meaning the return has to be inverted
        return !$found;
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

        // We found a new character, let the webhooks know
        $this->webhooks->sendToNewCharactersFound("{$characterData['name']} / {$characterData['corporation_name']} | <https://eve-kill.com/character/{$characterData['character_id']}>");
        if ($deleted === false && isset($characterData['name'])) {
            $this->indexCharacterInSearch($characterData);
            $this->emitCharacterWS->enqueue($characterData);
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

    protected function fetchCharacter(int $characterId): array
    {
        if ($characterId < 10000) {
            return [];
        }

        $characterData = $this->esiFetcher->fetch('/latest/characters/' . $characterId);
        $characterData = json_validate($characterData['body']) ? json_decode($characterData['body'], true) : [];
        $characterData['character_id'] = $characterId;

        ksort($characterData);

        $this->characters->setData($characterData);
        $this->characters->save();

        return $characterData;
    }
}
