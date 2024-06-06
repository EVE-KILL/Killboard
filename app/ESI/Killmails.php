<?php

namespace EK\ESI;

class Killmails
{
    public function __construct(
        protected EsiFetcher $esiFetcher
    ) {
    }

    public function getKillmail(int $killmail_id, string $hash): array
    {
        return $this->esiFetcher->fetch('/latest/killmails/' . $killmail_id . '/' . $hash);
    }
}
