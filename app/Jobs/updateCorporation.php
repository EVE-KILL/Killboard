<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;

class updateCorporation extends Jobs
{
    public function __construct(
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Characters $characters,
        protected \EK\models\Stations $stations,
        protected \EK\Models\Factions $factions,
        protected \EK\ESI\Alliances $esiAlliances,
        protected \EK\ESI\Characters $esiCharacters,
        protected \EK\ESI\Stations $esiStations,
        protected \EK\Redis\Redis $redis
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $corporationId = $data['corporation_id'];

        $corporationData = $this->corporations->findOne(['corporation_id' => $corporationId])->toArray();

        $allianceId = $corporationData['alliance_id'] ?? 0;
        $factionId = $corporationData['faction_id'] ?? 0;
        $ceoId = $corporationData['ceo_id'] ?? 0;
        $creatorId = $corporationData['creator_id'] ?? 0;
        $homeStationId = $corporationData['home_station_id'] ?? 0;

        $allianceData = [];
        $factionData = [];
        $ceoData = [];
        $creatorData = [];
        $homeStationData = [];

        if ($allianceId > 0) {
            $allianceData = $this->alliances->findOneOrNull(['alliance_id' => $allianceId]) ??
                $this->esiAlliances->getAllianceInfo($allianceId);
        }

        if ($factionId > 0) {
            $factionData = $this->factions->findOne(['faction_id' => $factionId]);
        }

        if ($ceoId > 0) {
            $ceoData = $this->characters->findOneOrNull(['character_id' => $ceoId]) ??
                $this->esiCharacters->getCharacterInfo($ceoId);
        }

        if ($creatorId > 0) {
            $creatorData = $this->characters->findOneOrNull(['character_id' => $creatorId]) ??
                $this->esiCharacters->getCharacterInfo($creatorId);
        }

        if ($homeStationId > 0) {
            $homeStationData = $this->stations->findOneOrNull(['station_id' => $homeStationId]) ??
                $this->esiStations->getStationInfo($homeStationId);
        }

        $corporationData['alliance_name'] = $allianceData['name'] ?? '';
        $corporationData['ceo_name'] = $ceoData['name'] ?? '';
        $corporationData['creator_name'] = $creatorData['name'] ?? '';
        $corporationData['home_station_name'] = $homeStationData['name'] ?? '';
        $corporationData['faction_name'] = $factionData['name'] ?? '';

        ksort($corporationData);

        $this->corporations->setData($corporationData);
        $this->corporations->save();
    }
}