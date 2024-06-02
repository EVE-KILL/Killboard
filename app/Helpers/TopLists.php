<?php

namespace EK\Helpers;

use EK\Cache\Cache;
use MongoDB\BSON\UTCDateTime;

class TopLists
{
    public function __construct(
        protected \EK\Models\Killmails $killmails,
        protected \EK\Models\Characters $characters,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\TypeIDs $typeIDs,
        protected \EK\Models\SolarSystems $solarSystems,
        protected \EK\Models\Constellations $constellations,
        protected \EK\Models\Regions $regions,
        protected Cache $cache
    ) {

    }

    public function topCharacters(string $attackerType, int $typeId, int $limit = 10): array
    {
        $cacheKey = $this->cache->generateKey('top_characters', $attackerType, $typeId, $limit);
        if ($this->cache->exists($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $data = $this->killmails->aggregate([
            [
                '$match' => [
                    "attackers.{$attackerType}" => $typeId,
                    'attackers.character_id' => ['$ne' => 0],
                    'kill_time' => ['$gte' => new UTCDateTime((time() - 2592000) * 1000)]
                ]
            ],
            ['$unwind' => '$attackers'],
            ['$match' => ["attackers.{$attackerType}" => $typeId]],
            ['$group' => ['_id' => '$attackers.character_id', 'count' => ['$sum' => 1]]],
            ['$project' => ['_id' => 0, 'count' => '$count', 'id' => '$_id']],
            ['$sort' => ['count' => -1]],
            ['$limit' => $limit],
        ], [
            'allowDiskUse' => true,
            'maxTimeMS' => 30000
        ]);

        foreach($data as $key => $character) {
            $data[$key] = array_merge(
                ['count' => $character['count']],
                $this->characters->findOne(['character_id' => $character['id']])->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, 300);

        return $data->toArray();
    }

    public function topCorporations(string $attackerType, int $typeId, int $limit = 10): array
    {
        $cacheKey = $this->cache->generateKey('top_corporations', $attackerType, $typeId, $limit);
        if ($this->cache->exists($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $data = $this->killmails->aggregate([
            [
                '$match' => [
                    "attackers.{$attackerType}" => $typeId,
                    'attackers.corporation_id' => ['$ne' => 0],
                    'kill_time' => ['$gte' => new UTCDateTime((time() - 2592000) * 1000)]
                ]
            ],
            ['$unwind' => '$attackers'],
            ['$match' => ["attackers.{$attackerType}" => $typeId]],
            ['$group' => ['_id' => '$attackers.corporation_id', 'count' => ['$sum' => 1]]],
            ['$project' => ['_id' => 0, 'count' => '$count', 'id' => '$_id']],
            ['$sort' => ['count' => -1]],
            ['$limit' => $limit],
        ], [
            'allowDiskUse' => true,
            'maxTimeMS' => 30000
        ]);

        foreach($data as $key => $corporation) {
            $data[$key] = array_merge(
                ['count' => $corporation['count']],
                $this->corporations->findOne(['corporation_id' => $corporation['id']])->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, 300);

        return $data->toArray();
    }

    public function topAlliances(string $attackerType, int $typeId, int $limit = 10): array
    {
        $cacheKey = $this->cache->generateKey('top_alliances', $attackerType, $typeId, $limit);
        if ($this->cache->exists($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $data = $this->killmails->aggregate([
            [
                '$match' => [
                    "attackers.{$attackerType}" => $typeId,
                    'attackers.alliance_id' => ['$ne' => 0],
                    'kill_time' => ['$gte' => new UTCDateTime((time() - 2592000) * 1000)]
                ]
            ],
            ['$unwind' => '$attackers'],
            ['$match' => ["attackers.{$attackerType}" => $typeId]],
            ['$group' => ['_id' => '$attackers.alliance_id', 'count' => ['$sum' => 1]]],
            ['$project' => ['_id' => 0, 'count' => '$count', 'id' => '$_id']],
            ['$sort' => ['count' => -1]],
            ['$limit' => $limit],
        ], [
            'allowDiskUse' => true,
            'maxTimeMS' => 30000
        ]);

        foreach($data as $key => $alliance) {
            $data[$key] = array_merge(
                ['count' => $alliance['count']],
                $this->alliances->findOne(['alliance_id' => $alliance['id']])->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, 300);

        return $data->toArray();
    }

    public function topShips(string $attackerType, int $typeId, int $limit = 10): array
    {
        $cacheKey = $this->cache->generateKey('top_ships', $attackerType, $typeId, $limit);
        if ($this->cache->exists($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $data = $this->killmails->aggregate([
            [
                '$match' => [
                    "attackers.{$attackerType}" => $typeId,
                    'kill_time' => ['$gte' => new UTCDateTime((time() - 2592000) * 1000)]
                ]
            ],
            ['$unwind' => '$attackers'],
            ['$match' => ["attackers.{$attackerType}" => $typeId]],
            ['$group' => ['_id' => '$attackers.ship_id', 'count' => ['$sum' => 1]]],
            ['$project' => ['_id' => 0, 'count' => '$count', 'id' => '$_id']],
            ['$sort' => ['count' => -1]],
            ['$limit' => $limit],
        ], [
            'allowDiskUse' => true,
            'maxTimeMS' => 30000
        ]);

        foreach($data as $key => $ship) {
            $data[$key] = array_merge(
                ['count' => $ship['count']],
                $this->typeIDs->findOne(['type_id' => $ship['id']])->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, 300);

        return $data->toArray();
    }

    public function topSystems(string $attackerType, int $typeId, int $limit = 10): array
    {
        $cacheKey = $this->cache->generateKey('top_systems', $attackerType, $typeId, $limit);
        if ($this->cache->exists($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $data = $this->killmails->aggregate([
            [
                '$match' => [
                    "attackers.{$attackerType}" => $typeId,
                    'kill_time' => ['$gte' => new UTCDateTime((time() - 2592000) * 1000)]
                ]
            ],
            ['$unwind' => '$attackers'],
            ['$match' => ["attackers.{$attackerType}" => $typeId]],
            ['$group' => ['_id' => '$system_id', 'count' => ['$sum' => 1]]],
            ['$project' => ['_id' => 0, 'count' => '$count', 'id' => '$_id']],
            ['$sort' => ['count' => -1]],
            ['$limit' => $limit],
        ], [
            'allowDiskUse' => true,
            'maxTimeMS' => 30000
        ]);

        foreach($data as $key => $system) {
            $data[$key] = array_merge(
                ['count' => $system['count']],
                $this->solarSystems->findOne(['system_id' => $system['id']])->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, 300);

        return $data->toArray();
    }

    public function topRegions(string $attackerType, int $typeId, int $limit = 10): array
    {
        $cacheKey = $this->cache->generateKey('top_regions', $attackerType, $typeId, $limit);
        if ($this->cache->exists($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $data = $this->killmails->aggregate([
            [
                '$match' => [
                    "attackers.{$attackerType}" => $typeId,
                    'kill_time' => ['$gte' => new UTCDateTime((time() - 2592000) * 1000)]
                ]
            ],
            ['$unwind' => '$attackers'],
            ['$match' => ["attackers.{$attackerType}" => $typeId]],
            ['$group' => ['_id' => '$region_id', 'count' => ['$sum' => 1]]],
            ['$project' => ['_id' => 0, 'count' => '$count', 'id' => '$_id']],
            ['$sort' => ['count' => -1]],
            ['$limit' => $limit],
        ], [
            'allowDiskUse' => true,
            'maxTimeMS' => 30000
        ]);

        foreach($data as $key => $region) {
            $data[$key] = array_merge(
                ['count' => $region['count']],
                $this->regions->findOne(['region_id' => $region['id']])->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, 300);

        return $data->toArray();
    }
}