<?php

namespace EK\Helpers;

use EK\Cache\Cache;
use EK\Models\Killmails;
use Illuminate\Support\Collection as IlluminateCollection;

class KillList
{
    public function __construct(
        protected Killmails $killmails,
        protected Cache $cache
    ) {
    }

    public function getLatest(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("latest_killlist", $page, $offset);
        return $this->fetchData(
            [],
            [
                "hint" => "kill_time",
                "sort" => ["kill_time" => -1],
                "projection" => ["_id" => 0, "items" => 0],
                "skip" => $offset,
                "limit" => $limit,
            ],
            $cacheKey,
            $cacheTime
        );
    }

    public function getKillsForType(string $type, int $value, int $page = 1, int $limit = 1000, int $cacheTime = 60): IlluminateCollection
    {
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
        $cacheKey = $this->cache->generateKey("killlist_for_type", $type, $value, $page, $offset);
        return $this->fetchData(
            [$type => $value],
            [
                "sort" => ["kill_time" => -1],
                "projection" => ["_id" => 0, "items" => 0],
                "skip" => $offset,
                "limit" => $limit,
            ],
            $cacheKey,
            $cacheTime
        );
    }

    private function fetchData(array $find = [], array $options = [], string $cacheKey = '', int $cacheTime = 60): IlluminateCollection
    {
        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return collect($cacheResult);
        }

        $result = $this->killmails->find($find, $options);

        $this->cache->set($cacheKey, $result, $cacheTime);
        return collect($result);
    }

    public function getAbyssal(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_abyssal", $page);
        return $this->fetchData([
            'region_id' => ['$gte' => 12000000, '$lte' => 13000000]
        ],
        [
            "hint" => "kill_time_region_id",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getWSpace(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_wspace", $page);
        return $this->fetchData([
            'region_id' => ['$gte' => 11000001, '$lte' => 11000033]
        ],
        [
            "hint" => "kill_time_region_id",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getHighSec(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_highsec", $page);
        return $this->fetchData([
            'system_security' => ['$gte' => 0.45]
        ],
        [
            "hint" => "system_security_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getLowSec(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_lowsec", $page);
        return $this->fetchData([
            'system_security' => ['$lte' => 0.45, '$gte' => 0]
        ],
        [
            "hint" => "system_security_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getNullSec(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_nullsec", $page);
        return $this->fetchData([
            'system_security' => ['$lte' => 0]
        ],
        [
            "hint" => "system_security_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getBigKills(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_bigkills", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [547, 485, 513, 902, 941, 30, 659]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getSolo(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_solo", $page);
        return $this->fetchData([
            'is_solo' => true
        ],
        [
            "hint" => "is_solo_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getNPC(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_npc", $page);
        return $this->fetchData([
            'is_npc' => true
        ],
        [
            "hint" => "is_npc_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function get5b(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_5b", $page);
        return $this->fetchData([
            'total_value' => ['$gte' => 5000000000]
        ],
        [
            "hint" => "total_value_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function get10b(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_10b", $page);
        return $this->fetchData([
            'total_value' => ['$gte' => 10000000000]
        ],
        [
            "hint" => "total_value_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getCitadels(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_citadels", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [1657]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getT1(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_t1", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [419, 27, 29, 547, 26, 420, 25, 28, 941, 463, 237, 31]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getT2(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_t2", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [324, 898, 906, 540, 830, 893, 543, 541, 833, 358, 894, 831, 902, 832, 900, 834, 380]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getT3(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_t3", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [963, 1305]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getFrigates(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_frigates", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [324, 893, 25, 831, 237]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getDestroyers(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_destroyers", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [420, 541]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getCruisers(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_cruisers", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [906, 26, 833, 358, 894, 832, 963]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getBattleCruisers(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_battlecruisers", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [419, 540]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getBattleShips(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_battleships", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [27, 898, 900]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getCapitals(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_capitals", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [547, 485]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getFreighters(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_freighters", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [513, 902]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getSuperCarriers(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_supercarriers", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [659]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }

    public function getTitans(int $page = 1, int $limit = 100, int $cacheTime = 60): IlluminateCollection
    {
        $offset = $limit * ($page - 1);
        $cacheKey = $this->cache->generateKey("killlist_titans", $page);
        return $this->fetchData([
            'victim.ship_group_id' => ['$in' => [30]]
        ],
        [
            "hint" => "victim.ship_group_id_kill_time",
            "sort" => ["kill_time" => -1],
            "projection" => ["_id" => 0, "items" => 0],
            "skip" => $offset,
            "limit" => $limit,
        ],
        $cacheKey,
        $cacheTime
        );
    }
}
