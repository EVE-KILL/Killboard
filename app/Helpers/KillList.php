<?php

namespace EK\Helpers;

use EK\Cache\Cache;
use EK\Database\Collection;
use EK\Models\Killmails;
use Illuminate\Support\Collection as IlluminateCollection;

class KillList
{
    public function __construct(
        protected Killmails $killmails,
        protected Cache $cache
    ) {
    }

    public function getLatest(
        int $page = 1,
        int $limit = 100,
        int $cacheTime = 60
    ): IlluminateCollection {
        $offset = $limit * ($page - 1);

        $cacheKey = $this->cache->generateKey(
            "latest_killlist",
            $page,
            $offset
        );
        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return collect($cacheResult);
        }

        $result = $this->killmails->find(
            [],
            [
                "hint" => "kill_time",
                "sort" => ["kill_time" => -1],
                "projection" => ["_id" => 0, "items" => 0],
                "skip" => $offset,
                "limit" => $limit,
            ]
        );

        $this->cache->set($cacheKey, $result, $cacheTime);
        return $result;
    }

    public function getKillsForType(
        string $type,
        int $value,
        int $page = 1,
        int $limit = 1000,
        int $cacheTime = 60
    ): IlluminateCollection {
        $validTypes = [
            "attackers.character_id",
            "attackers.corporation_id",
            "attackers.alliance_id",
            "attackers.faction_id",
            "attackers.ship_id",
            "attackers.weapon_type_id",
            "victim.character_id",
            "victim.corporation_id",
            "victim.alliance_id",
            "victim.faction_id",
            "victim.ship_id",
            "victim.weapon_type_id",
            "war_id",
            "region_id",
            "system_id",
        ];

        if (!in_array($type, $validTypes)) {
            return collect(["error" => "Invalid type provided"]);
        }

        $offset = $limit * ($page - 1);

        $cacheKey = $this->cache->generateKey(
            "killlist_for_type",
            $type,
            $value,
            $page,
            $offset
        );
        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return collect($cacheResult);
        }

        $result = $this->killmails->find(
            [$type => $value],
            [
                "sort" => ["kill_time" => -1],
                "projection" => ["_id" => 0, "items" => 0],
                "skip" => $offset,
                "limit" => $limit,
            ]
        );

        $this->cache->set($cacheKey, $result, $cacheTime);
        return $result;
    }
}
