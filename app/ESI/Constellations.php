<?php

namespace EK\ESI;

use EK\Fetchers\ESI;

class Constellations
{
    public function __construct(
        protected \EK\Models\Constellations $constellations,
        protected \EK\Models\Regions $regions,
        protected \EK\ESI\Regions $esiRegions,
        protected ESI $esiFetcher
    ) {
    }

    public function getConstellation(int $constellation_id): array
    {
        $constellationData = $this->esiFetcher->fetch('/latest/universe/constellations/' . $constellation_id);
        $constellationData = json_validate($constellationData['body']) ? json_decode($constellationData['body'], true) : [];
        $regionData = $this->regions->findOneOrNull(['region_id' => $constellationData['region_id']]) ??
            $this->esiRegions->getRegion($constellationData['region_id']);

        $constellationData['region_name'] = $regionData['name'];

        ksort($constellationData);

        $this->constellations->setData($constellationData);
        $this->constellations->save();

        return $constellationData;
    }
}
