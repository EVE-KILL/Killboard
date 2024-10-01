<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use EK\Models\Alliances as ModelsAlliances;
use League\Container\Container;

class Alliances
{
    public function __construct(
        protected Container $container,
        protected ModelsAlliances $alliances,
        protected ESI $esiFetcher
    ) {
    }

    public function getAllianceInfo(int $allianceId, int $cacheTime = 300): array
    {
        if ($allianceId < 10000) {
            return [
                'alliance_id' => $allianceId,
                'name' => 'Unknown',
                'creator_corporation_id' => 0,
                'executor_corporation_id' => 0,
                'faction_id' => 0,
            ];
        }

        $allianceData = $this->esiFetcher->fetch('/latest/alliances/' . $allianceId, cacheTime: $cacheTime);
        $allianceData = json_validate($allianceData['body']) ? json_decode($allianceData['body'], true) : [];
        $allianceData['alliance_id'] = $allianceId;

        ksort($allianceData);

        return $allianceData;
    }
}
