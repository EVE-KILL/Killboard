<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Meilisearch\Meilisearch;
use Illuminate\Support\Collection;

class updateCharacter extends Jobs
{
    protected string $defaultQueue = 'character';
    public function __construct(
        protected \EK\Models\Characters $characters,
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Factions $factions,
        protected \EK\ESI\Alliances $esiAlliances,
        protected \EK\ESI\Corporations $esiCorporations,
        protected \EK\ESI\Characters $esiCharacters,
        protected \EK\ESI\EsiFetcher $esiFetcher,
        protected Meilisearch $meilisearch,
        protected \EK\Redis\Redis $redis
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $characterId = $data['character_id'];

        $characterData = $this->characters->findOneOrNull(['character_id' => $characterId]) ??
            $this->esiCharacters->getCharacterInfo($characterId);
        $characterData = $characterData instanceof Collection ? $characterData->toArray() : $characterData;

        $allianceId = $characterData['alliance_id'] ?? 0;
        $corporationId = $characterData['corporation_id'] ?? 0;
        $factionId = $characterData['faction_id'] ?? 0;

        $allianceData = [];
        $factionData = [];

        if ($allianceId > 0) {
            $allianceData = $this->alliances->findOneOrNull(['alliance_id' => $allianceId]) ??
                $this->esiAlliances->getAllianceInfo($allianceId);
        }

        if ($corporationId > 0) {
            $corporationData = $this->corporations->findOneOrNull(['corporation_id' => $corporationId]) ??
                $this->esiCorporations->getCorporationInfo($corporationId);
        }

        if ($factionId > 0) {
            $factionData = $this->factions->findOne(['faction_id' => $factionId]);
        }

        $characterData['alliance_name'] = $allianceData['name'] ?? '';
        $characterData['corporation_name'] = $corporationData['name'] ?? '';
        $characterData['faction_name'] = $factionData['name'] ?? '';

        // Get character corporation history and attach it to the character data
        $corporationHistoryRequest = $this->esiFetcher->fetch('/latest/characters/' . $characterId . '/corporationhistory');
        $characterData['history'] = json_validate($corporationHistoryRequest['body']) ? json_decode($corporationHistoryRequest['body'], true) : [];

        ksort($characterData);

        $this->characters->setData($characterData);
        $this->characters->save();

        // Push the alliance to the search index
        $this->meilisearch->addDocuments([
            'id' => $characterData['character_id'],
            'name' => $characterData['name'],
            'type' => 'character'
        ]);
    }
}