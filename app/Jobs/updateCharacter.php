<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;

class updateCharacter extends Jobs
{
    public function __construct(
        protected \EK\Models\Characters $characters,
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Factions $factions,
        protected \EK\ESI\Alliances $esiAlliances,
        protected \EK\ESI\Corporations $esiCorporations,
        protected \EK\Redis\Redis $redis
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $characterId = $data['character_id'];

        $characterData = $this->characters->findOne(['character_id' => $characterId])->toArray();

        $allianceId = $characterData['alliance_id'] ?? 0;
        $corporationId = $characterData['corporation_id'];
        $factionId = $characterData['faction_id'] ?? 0;

        $allianceData = [];
        $factionData = [];

        if ($allianceId > 0) {
            $allianceData = $this->alliances->findOneOrNull(['alliance_id' => $allianceId]) ??
                $this->esiAlliances->getAllianceInfo($allianceId);
        }

        if ($factionId > 0) {
            $factionData = $this->factions->findOne(['faction_id' => $factionId]);
        }

        $corporationData = $this->corporations->findOneOrNull(['corporation_id' => $corporationId]) ??
            $this->esiCorporations->getCorporationInfo($corporationId);

        $characterData['alliance_name'] = $allianceData['name'] ?? '';
        $characterData['corporation_name'] = $corporationData['name'];
        $characterData['faction_name'] = $factionData['name'] ?? '';

        ksort($characterData);

        $this->characters->setData($characterData);
        $this->characters->save();
    }
}