<?php

namespace EK\ESI;

use EK\ESI\Regions as ESIRegions;
use EK\Fetchers\ESI;
use EK\Models\Constellations as ModelsConstellations;
use EK\Models\Regions;

class Constellations
{
    public function __construct(
        protected ModelsConstellations $constellations,
        protected Regions $regions,
        protected ESIRegions $esiRegions,
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
