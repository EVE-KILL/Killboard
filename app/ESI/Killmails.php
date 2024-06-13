<?php

namespace EK\ESI;

use EK\Fetchers\ESI;

class Killmails
{
    public function __construct(
        protected ESI $esiFetcher
    ) {
    }

    public function getKillmail(int $killmail_id, string $hash): array
    {
        $result = $this->esiFetcher->fetch('/latest/killmails/' . $killmail_id . '/' . $hash);
        $result = json_validate($result['body']) ? json_decode($result['body'], true) : [];
        ksort($result);

        return $result;
    }
}
