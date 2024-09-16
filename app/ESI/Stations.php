<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use EK\Models\Stations as ModelsStations;

class Stations
{
    public function __construct(
        protected ModelsStations $stations,
        protected ESI $esiFetcher
    ) {
    }

    public function getStationInfo(int $stationID): array
    {
        $stationData = $this->esiFetcher->fetch('/latest/universe/stations/' . $stationID);
        $stationData = json_validate($stationData['body']) ? json_decode($stationData['body'], true) : [];

        ksort($stationData);

        $this->stations->setData($stationData);
        $this->stations->save();

        return $stationData;
    }
}
