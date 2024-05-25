<?php

namespace EK\ESI;

use EK\Api\Abstracts\ESIInterface;

class Regions extends ESIInterface
{
    public function __construct(
        protected \EK\Models\Regions $regions,
        protected EsiFetcher $esiFetcher
    ) {
        parent::__construct($esiFetcher);
    }

    public function getRegion(int $region_id): array
    {
        $result = $this->fetch('/latest/universe/regions/' . $region_id);
        ksort($result);
        $this->regions->setData($result);
        $this->regions->save();

        return $result;
    }
}
