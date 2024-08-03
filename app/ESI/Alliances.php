<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use League\Container\Container;

class Alliances
{
    public function __construct(
        protected Container $container,
        protected \EK\Models\Alliances $alliances,
        protected ESI $esiFetcher
    ) {
    }

    public function getAllianceInfo(int $allianceID): array
    {
        if ($allianceID < 10000) {
            return [];
        }

        $allianceData = $this->esiFetcher->fetch('/latest/alliances/' . $allianceID);
        $allianceData = json_validate($allianceData['body']) ? json_decode($allianceData['body'], true) : [];
        $allianceData['alliance_id'] = $allianceID;

        ksort($allianceData);

        $this->alliances->setData($allianceData);
        $this->alliances->save();

        $updateAlliance = $this->container->get(\EK\Jobs\UpdateAlliance::class);
        $updateAlliance->enqueue(['alliance_id' => $allianceData['alliance_id']]);

        return $allianceData;
    }
}
