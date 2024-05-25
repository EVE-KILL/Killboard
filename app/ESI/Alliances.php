<?php

namespace EK\ESI;

use EK\Api\Abstracts\ESIInterface;
use League\Container\Container;

class Alliances extends ESIInterface
{
    public function __construct(
        protected Container $container,
        protected \EK\Models\Alliances $alliances,
        protected EsiFetcher $esiFetcher
    ) {
        parent::__construct($esiFetcher);
    }

    public function getAllianceInfo(int $allianceID): array
    {
        $allianceData = $this->fetch('/latest/alliances/' . $allianceID);
        $allianceData['alliance_id'] = $allianceID;

        ksort($allianceData);

        $this->alliances->setData($allianceData);
        $this->alliances->save();

        $updateAlliance = $this->container->get(\EK\Jobs\updateAlliance::class);
        $updateAlliance->enqueue(['alliance_id' => $allianceData['alliance_id']]);

        return $allianceData;
    }
}
