<?php

namespace EK\Helpers;

use EK\Database\Collection;
use EK\Models\Killmails;
use Illuminate\Support\Collection as IlluminateCollection;

class KillList
{
    public function __construct(
        protected Killmails $killmails,
    ) {

    }

    public function getLatest(int $page = 1, int $limit = 100): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        return $this->killmails->find([], [
            'hint' => 'kill_time',
            'sort' => ['kill_time' => -1],
            'projection' => ['_id' => 0, 'items' => 0],
            'skip' => $offset,
            'limit' => $limit
        ]);
    }

    public function getKillsForType(string $type, int $value, int $page = 1, int $limit = 1000): IlluminateCollection
    {
        $validTypes = [
            'attackers.character_id',
            'attackers.corporation_id',
            'attackers.alliance_id',
            'attackers.faction_id',
            'attackers.ship_id',
            'attackers.weapon_type_id',
            'victim.character_id',
            'victim.corporation_id',
            'victim.alliance_id',
            'victim.faction_id',
            'victim.ship_id',
            'victim.weapon_type_id',
            'war_id',
            'region_id',
            'system_id',
        ];

        if (!in_array($type, $validTypes)) {
            return collect(['error' => 'Invalid type provided']);
        }

        $offset = $limit * ($page - 1);
        return $this->killmails->find([$type => $value], [
            'sort' => ['kill_time' => -1],
            'projection' => ['_id' => 0, 'items' => 0],
            'skip' => $offset,
            'limit' => $limit
        ]);
    }
}