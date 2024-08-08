<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use League\Container\Container;

class Corporations
{
    public function __construct(
        protected Container $container,
        protected \EK\Models\Corporations $corporations,
        protected ESI $esiFetcher
    ) {
    }

    public function getCorporationInfo(int $corporationId): array
    {
        if ($corporationId < 10000) {
            return [];
        }

        $data = $this->esiFetcher->fetch('/latest/corporations/' . $corporationId);
        $corporationData = json_validate($data['body']) ? json_decode($data['body'], true) : [];
        $corporationData['corporation_id'] = $corporationId;

        ksort($corporationData);

        $this->corporations->setData($corporationData);
        $this->corporations->save();

        return $corporationData;
    }
}
