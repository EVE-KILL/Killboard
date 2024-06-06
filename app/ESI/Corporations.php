<?php

namespace EK\ESI;

use League\Container\Container;

class Corporations
{
    public function __construct(
        protected Container $container,
        protected \EK\Models\Corporations $corporations,
        protected EsiFetcher $esiFetcher
    ) {
        parent::__construct($esiFetcher);
    }

    public function getCorporationInfo(int $corporationId): array
    {
        if ($corporationId < 10000) {
            return [];
        }

        $corporationData = $this->esiFetcher->fetch('/latest/corporations/' . $corporationId);
        $corporationData['corporation_id'] = $corporationId;

        ksort($corporationData);

        $this->corporations->setData($corporationData);
        $this->corporations->save();

        $updateCorporation = $this->container->get(\EK\Jobs\updateCorporation::class);
        $updateCorporation->enqueue(['corporation_id' => $corporationData['corporation_id']]);

        return $corporationData;
    }
}
