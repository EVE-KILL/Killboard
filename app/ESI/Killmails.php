<?php

namespace EK\ESI;

use EK\Api\Abstracts\ESIInterface;
use Illuminate\Support\Collection;

class Killmails extends ESIInterface
{
    public function getKillmail(int $killmail_id, string $hash): array
    {
        return $this->fetch('/latest/killmails/' . $killmail_id . '/' . $hash);
    }
}
