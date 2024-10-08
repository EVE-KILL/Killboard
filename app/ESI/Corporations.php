<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use EK\Fetchers\History;
use EK\Models\Corporations as ModelsCorporations;
use League\Container\Container;

class Corporations
{
    public function __construct(
        protected Container $container,
        protected ModelsCorporations $corporations,
        protected ESI $esiFetcher,
        protected History $history
    ) {
    }

    public function getCorporationInfo(int $corporationId): array
    {
        $data = $this->esiFetcher->fetch('/latest/corporations/' . $corporationId);
        $corporationData = json_validate($data['body']) ? json_decode($data['body'], true) : [];
        $corporationData['corporation_id'] = $corporationId;

        ksort($corporationData);

        return $corporationData;
    }

    public function getCorporationHistory(int $corporationId, int $cacheTime = 300): array
    {
        $data = $this->history->fetch('/latest/corporations/' . $corporationId . '/alliancehistory', cacheTime: $cacheTime);
        $corporationHistory = json_validate($data['body']) ? json_decode($data['body'], true) : [];

        ksort($corporationHistory);

        return $corporationHistory;
    }
}
