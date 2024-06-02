<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Meilisearch\Meilisearch;
use EK\Redis\Redis;
use Illuminate\Support\Collection;

class updateAlliance extends Jobs
{
    protected string $defaultQueue = 'alliance';
    public function __construct(
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Characters $characters,
        protected \EK\Models\Factions $factions,
        protected \EK\ESI\Alliances $esiAlliances,
        protected \EK\ESI\Corporations $esiCorporations,
        protected \EK\ESI\Characters $esiCharacters,
        protected Meilisearch $meilisearch,
        protected Redis $redis
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $allianceId = $data['alliance_id'];

        $allianceData = $this->alliances->findOneOrNull(['alliance_id' => $allianceId]) ??
            $this->esiAlliances->getAllianceInfo($allianceId);
        $allianceData = $allianceData instanceof Collection ? $allianceData->toArray() : $allianceData;

        $creatorCorporationId = $allianceData['creator_corporation_id'];
        $executor_CorporationId = $allianceData['executor_corporation_id'];
        $creatorCharacterId = $allianceData['creator_id'];
        $factionId = $allianceData['faction_id'] ?? 0;
        $factionData = [];

        if ($factionId > 0) {
            $factionData = $this->factions->findOne(['faction_id' => $factionId]);
        }

        $creatorCorporationData = $this->corporations->findOneOrNull(['corporation_id' => $creatorCorporationId]) ??
            $this->esiCorporations->getCorporationInfo($creatorCorporationId);

        $executorCorporationData = $this->corporations->findOneOrNull(['corporation_id' => $executor_CorporationId]) ??
            $this->esiCorporations->getCorporationInfo($executor_CorporationId);

        $creatorCharacterData = $this->characters->findOneOrNull(['character_id' => $creatorCharacterId]) ??
            $this->esiCharacters->getCharacterInfo($creatorCharacterId);

        $allianceData['creator_corporation_name'] = $creatorCorporationData['name'];
        $allianceData['executor_corporation_name'] = $executorCorporationData['name'];
        $allianceData['creator_name'] = $creatorCharacterData['name'];
        $allianceData['faction_name'] = $factionData['name'] ?? '';

        ksort($allianceData);

        $this->alliances->setData($allianceData);
        $this->alliances->save();

        // Push the alliance to the search index
        $this->meilisearch->addDocuments([
            'id' => $allianceData['alliance_id'],
            'name' => $allianceData['name'],
            'type' => 'alliance'
        ]);
    }
}