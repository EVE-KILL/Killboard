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

    public function getAllianceInfo(int $allianceId): array
    {
        $allianceData = $this->esiFetcher->fetch('/latest/alliances/' . $allianceId);
        $allianceData = json_validate($allianceData['body']) ? json_decode($allianceData['body'], true) : [];
        $allianceData['alliance_id'] = $allianceId;

        ksort($allianceData);

        return $allianceData;
    }
}
