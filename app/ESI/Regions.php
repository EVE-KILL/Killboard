<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use EK\Models\Regions as ModelsRegions;

class Regions
{
    public function __construct(
        protected ModelsRegions $regions,
        protected ESI $esiFetcher
    ) {
    }

    public function getRegion(int $region_id): array
    {
        $result = $this->esiFetcher->fetch('/latest/universe/regions/' . $region_id);
        $result = json_validate($result['body']) ? json_decode($result['body'], true) : [];
        ksort($result);
        $this->regions->setData($result);
        $this->regions->save();

        return $result;
    }
}
