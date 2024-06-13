<?php

namespace EK\ESI;

use EK\Fetchers\ESI;

class SolarSystems
{
    public function __construct(
        protected \EK\Models\SolarSystems $solarSystems,
        protected \EK\Models\Constellations $constellations,
        protected \EK\Models\Regions $regions,
        protected \EK\ESI\Constellations $esiConstellations,
        protected \EK\ESI\Regions $esiRegions,
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
