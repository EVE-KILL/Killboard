<?php

namespace EK\ESI;

use EK\Api\Abstracts\ESIInterface;

class SolarSystems extends ESIInterface
{
    public function __construct(
        protected \EK\Models\SolarSystems $solarSystems,
        protected \EK\Models\Constellations $constellations,
        protected \EK\Models\Regions $regions,
        protected \EK\ESI\Constellations $esiConstellations,
        protected \EK\ESI\Regions $esiRegions,
        protected EsiFetcher $esiFetcher
    ) {
        parent::__construct($esiFetcher);
    }

    public function getSolarSystem(int $system_id): array
    {
        $systemData = $this->fetch('/latest/universe/systems/' . $system_id);
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
