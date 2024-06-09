<?php

namespace EK\ESI;

class Regions
{
    public function __construct(
        protected \EK\Models\Regions $regions,
        protected EsiFetcher $esiFetcher
    ) {
    }

    public function getRegion(int $region_id): array
    {
        $result = $this->esiFetcher->fetch('/latest/universe/regions/' . $region_id);
        ksort($result);
        $this->regions->setData($result);
        $this->regions->save();

        return $result;
    }
}
