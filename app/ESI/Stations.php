<?php

namespace EK\ESI;

use EK\Api\Abstracts\ESIInterface;

class Stations extends ESIInterface
{
    public function __construct(
        protected \EK\Models\Stations $stations,
        protected EsiFetcher $esiFetcher
    ) {
        parent::__construct($esiFetcher);
    }

    public function getStationInfo(int $stationID): array
    {
        $stationData = $this->fetch('/latest/universe/stations/' . $stationID);

        ksort($stationData);

        $this->stations->setData($stationData);
        $this->stations->save();

        return $stationData;
    }
}
