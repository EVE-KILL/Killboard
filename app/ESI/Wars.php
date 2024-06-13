<?php

namespace EK\ESI;

use EK\Fetchers\ESI;

class Wars
{
    public function __construct(
        protected ESI $esiFetcher
    ) {
    }

    public function getWars(int $maxWarId): array
    {
        $result = $this->esiFetcher->fetch('/latest/wars', 'GET', ['max_war_id' => $maxWarId]);
        $result = json_validate($result['body']) ? json_decode($result['body'], true) : [];
        ksort($result);

        return $result;
    }

    public function getWar(int $warId): array
    {
        $result = $this->esiFetcher->fetch("/latest/wars/{$warId}");
        $result = json_validate($result['body']) ? json_decode($result['body'], true) : [];
        ksort($result);

        return $result;
    }

    public function getWarKills(int $warId): array
    {
        $result = $this->esiFetcher->fetch("/latest/wars/{$warId}/killmails");
        $result = json_validate($result['body']) ? json_decode($result['body'], true) : [];
        ksort($result);

        return $result;
    }
}
