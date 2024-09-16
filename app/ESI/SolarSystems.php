<?php

namespace EK\ESI;

use EK\ESI\Constellations as ESIConstellations;
use EK\ESI\Regions as ESIRegions;
use EK\Fetchers\ESI;
use EK\Models\Constellations;
use EK\Models\Regions;
use EK\Models\SolarSystems as ModelsSolarSystems;

class SolarSystems
{
    public function __construct(
        protected ModelsSolarSystems $solarSystems,
        protected Constellations $constellations,
        protected Regions $regions,
        protected ESIConstellations $esiConstellations,
        protected ESIRegions $esiRegions,
        protected ESI $esiFetcher
    ) {
    }

    public function getSolarSystem(int $system_id): array
    {
        $systemData = $this->esiFetcher->fetch('/latest/universe/systems/' . $system_id);
        $systemData = json_validate($systemData['body']) ? json_decode($systemData['body'], true) : [];
        $constellationData = $this->constellations->findOneOrNull(['constellation_id' => $systemData['constellation_id']]) ??
            $this->esiConstellations->getConstellation($systemData['constellation_id']);
        $regionData = $this->regions->findOneOrNull(['region_id' => $constellationData['region_id']]) ??
            $this->esiRegions->getRegion($constellationData['region_id']);

        $systemData['constellation_name'] = $constellationData['name'];
        $systemData['region_name'] = $regionData['name'];
        $systemData['region_id'] = $regionData['region_id'];

        ksort($systemData);

        $this->solarSystems->setData($systemData);
        $this->solarSystems->save();

        return $systemData;
    }
}
