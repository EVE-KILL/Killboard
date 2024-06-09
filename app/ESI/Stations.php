<?php

namespace EK\ESI;

class Stations
{
    public function __construct(
        protected \EK\Models\Stations $stations,
        protected EsiFetcher $esiFetcher
    ) {
    }

    public function getStationInfo(int $stationID): array
    {
        $stationData = $this->esiFetcher->fetch('/latest/universe/stations/' . $stationID);

        ksort($stationData);

        $this->stations->setData($stationData);
        $this->stations->save();

        return $stationData;
    }
}
