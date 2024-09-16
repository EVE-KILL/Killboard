<?php

namespace EK\Helpers;

use EK\Cache\Cache;
use MongoDB\BSON\UTCDateTime;
use \EK\Models\Killmails;
use \EK\Models\Characters;
use \EK\Models\Corporations;
use \EK\Models\Alliances;
use \EK\Models\TypeIDs;
use \EK\Models\SolarSystems;
use \EK\Models\Constellations;
use \EK\Models\Regions;

class TopLists
{
    public function __construct(
        protected Killmails $killmails,
        protected Characters $characters,
        protected Corporations $corporations,
        protected Alliances $alliances,
        protected TypeIDs $typeIDs,
        protected SolarSystems $solarSystems,
        protected Constellations $constellations,
        protected Regions $regions,
        protected Cache $cache
    ) {
    }

    public function topCharacters(?string $attackerType = null, ?int $typeId = null, int $days = 30, int $limit = 10, int $cacheTime = 300): array
    {
        $cacheKey = $this->cache->generateKey(
            "top_characters",
            $attackerType,
            $typeId,
            $limit,
            $days
        );

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $cacheResult;
        }

        $calculatedTime = new UTCDateTime((time() - ($days * 86400)) * 1000);
        $aggregateQuery =
            $attackerType && $typeId
                ? [
                    [
                        '$match' => [
                            "attackers.{$attackerType}" => $typeId,
                            "attackers.character_id" => ['$ne' => 0],
                            "attackers.character_name" => ['$ne' => 'Unknown'],
                            "kill_time" => [
                                '$gte' => $calculatedTime,
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    ['$match' => ["attackers.{$attackerType}" => $typeId]],
                    [
                        '$group' => [
                            "_id" => [
                                'character_id' => '$attackers.character_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.character_id',
                            "count" => ['$sum' => 1],
                        ],
                    ],
                    [
                        '$project' => [
                            "_id" => 0,
                            "count" => '$count',
                            "id" => '$_id',
                        ],
                    ],
                    ['$sort' => ["count" => -1]],
                    ['$limit' => $limit],
                ]
                : [
                    [
                        '$match' => [
                            "attackers.character_id" => ['$ne' => 0],
                            "attackers.character_name" => ['$ne' => 'Unknown'],
                            "kill_time" => [
                                '$gte' => $calculatedTime,
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    [
                        '$group' => [
                            "_id" => [
                                'character_id' => '$attackers.character_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.character_id',
                            "count" => ['$sum' => 1],
                        ],
                    ],
                    [
                        '$project' => [
                            "_id" => 0,
                            "count" => '$count',
                            "id" => '$_id',
                        ],
                    ],
                    ['$sort' => ["count" => -1]],
                    ['$limit' => $limit],
                ];

        $dataGenerator = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        $result = [];

        foreach ($dataGenerator as $character) {
            $characterInfo = $this->characters->findOne(
                ["character_id" => $character["id"]],
                [
                    "projection" => [
                        "_id" => 0,
                        "last_modified" => 0,
                        "history" => 0,
                        "description" => 0,
                    ],
                ]
            );

            $result[] = array_merge(
                ["count" => $character["count"]],
                $characterInfo
            );
        }

        $this->cache->set($cacheKey, $result, $cacheTime);

        return $result;
    }

    public function topCorporations(?string $attackerType = null, int $typeId = null, int $days = 30, int $limit = 10, int $cacheTime = 300): array
    {
        $cacheKey = $this->cache->generateKey(
            "top_corporations",
            $attackerType,
            $typeId,
            $limit,
            $days
        );

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $cacheResult;
        }

        $calculatedTime = new UTCDateTime((time() - ($days * 86400)) * 1000);
        $aggregateQuery =
            $attackerType && $typeId
                ? [
                    [
                        '$match' => [
                            "attackers.{$attackerType}" => $typeId,
                            "attackers.corporation_id" => ['$ne' => 0],
                            "kill_time" => [
                                '$gte' => $calculatedTime,
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    ['$match' => ["attackers.{$attackerType}" => $typeId]],
                    [
                        '$group' => [
                            "_id" => [
                                'corporation_id' => '$attackers.corporation_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.corporation_id',
                            "count" => ['$sum' => 1],
                        ],
                    ],
                    [
                        '$project' => [
                            "_id" => 0,
                            "count" => '$count',
                            "id" => '$_id',
                        ],
                    ],
                    ['$sort' => ["count" => -1]],
                    ['$limit' => $limit],
                ]
                : [
                    [
                        '$match' => [
                            "attackers.corporation_id" => ['$ne' => 0],
                            "kill_time" => [
                                '$gte' => $calculatedTime,
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    [
                        '$group' => [
                            "_id" => [
                                'corporation_id' => '$attackers.corporation_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.corporation_id',
                            "count" => ['$sum' => 1],
                        ],
                    ],
                    [
                        '$project' => [
                            "_id" => 0,
                            "count" => '$count',
                            "id" => '$_id',
                        ],
                    ],
                    ['$sort' => ["count" => -1]],
                    ['$limit' => $limit],
                ];

        $dataGenerator = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        $result = [];

        foreach ($dataGenerator as $corporation) {
            $corporationInfo = $this->corporations->findOne(
                ["corporation_id" => $corporation["id"]],
                [
                    "projection" => [
                        "_id" => 0,
                        "last_modified" => 0,
                        "history" => 0,
                        "description" => 0,
                    ],
                ]
            );

            $result[] = array_merge(
                ["count" => $corporation["count"]],
                $corporationInfo
            );
        }

        $this->cache->set($cacheKey, $result, $cacheTime);

        return $result;
    }

    public function topAlliances(?string $attackerType = null, ?int $typeId = null, int $days = 30, int $limit = 10, int $cacheTime = 300): array
    {
        $cacheKey = $this->cache->generateKey(
            "top_alliances",
            $attackerType,
            $typeId,
            $limit,
            $days
        );

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $cacheResult;
        }

        $calculatedTime = new UTCDateTime((time() - ($days * 86400)) * 1000);
        $aggregateQuery =
            $attackerType && $typeId
                ? [
                    [
                        '$match' => [
                            "attackers.{$attackerType}" => $typeId,
                            "attackers.alliance_id" => ['$ne' => 0],
                            "kill_time" => [
                                '$gte' => $calculatedTime,
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    ['$match' => ["attackers.{$attackerType}" => $typeId]],
                    [
                        '$group' => [
                            "_id" => [
                                'alliance_id' => '$attackers.alliance_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.alliance_id',
                            "count" => ['$sum' => 1],
                        ],
                    ],
                    [
                        '$project' => [
                            "_id" => 0,
                            "count" => '$count',
                            "id" => '$_id',
                        ],
                    ],
                    ['$sort' => ["count" => -1]],
                    ['$limit' => $limit],
                ]
                : [
                    [
                        '$match' => [
                            "attackers.alliance_id" => ['$ne' => 0],
                            "kill_time" => [
                                '$gte' => $calculatedTime,
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    [
                        '$group' => [
                            "_id" => [
                                'alliance_id' => '$attackers.alliance_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.alliance_id',
                            "count" => ['$sum' => 1],
                        ],
                    ],
                    [
                        '$project' => [
                            "_id" => 0,
                            "count" => '$count',
                            "id" => '$_id',
                        ],
                    ],
                    ['$sort' => ["count" => -1]],
                    ['$limit' => $limit],
                ];

        $dataGenerator = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        $result = [];

        foreach ($dataGenerator as $alliance) {
            $allianceInfo = $this->alliances->findOne(
                ["alliance_id" => $alliance["id"]],
                [
                    "projection" => [
                        "_id" => 0,
                        "last_modified" => 0,
                    ],
                ]
            );

            $result[] = array_merge(
                ["count" => $alliance["count"]],
                $allianceInfo
            );
        }

        $this->cache->set($cacheKey, $result, $cacheTime);

        return $result;
    }

    public function topSolo(?string $attackerType = null, ?int $typeId = null, int $days = 30, int $limit = 10, int $cacheTime = 300): array
    {
        $cacheKey = $this->cache->generateKey(
            "top_solo",
            $attackerType,
            $typeId,
            $limit,
            $days
        );

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            // return $cacheResult;
        }

        $calculatedTime = new UTCDateTime((time() - ($days * 86400)) * 1000);

        $matchFilter = [
            "is_solo" => true,
            "kill_time" => ['$gte' => $calculatedTime],
        ];

        if ($attackerType && $typeId) {
            $matchFilter["attackers.{$attackerType}"] = $typeId;
        }

        $aggregateQuery = [
            ['$match' => $matchFilter],
            ['$unwind' => '$attackers'],
            [
                '$match' => [
                    'attackers.final_blow' => true,
                ]
            ],
            [
                '$group' => [
                    "_id" => '$attackers.character_id',
                    "count" => ['$sum' => 1],
                ],
            ],
            [
                '$project' => [
                    "_id" => 0,
                    "count" => '$count',
                    "character_id" => '$_id',
                ],
            ],
            ['$sort' => ["count" => -1]],
            ['$limit' => $limit],
        ];

        $dataGenerator = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        $result = [];

        foreach ($dataGenerator as $solo) {
            $characterInfo = $this->characters->findOne(
                ["character_id" => $solo["character_id"]],
                [
                    "projection" => [
                        "_id" => 0,
                        "last_modified" => 0,
                        "history" => 0,
                        "description" => 0,
                    ],
                ]
            );

            if ($characterInfo) {
                $result[] = array_merge(
                    ["count" => $solo["count"]],
                    $characterInfo
                );
            }
        }

        $this->cache->set($cacheKey, $result, $cacheTime);

        return $result;
    }

    public function topShips(?string $attackerType = null, ?int $typeId = null, int $days = 30, int $limit = 10, int $cacheTime = 300): array
    {
        $cacheKey = $this->cache->generateKey(
            "top_ships",
            $attackerType,
            $typeId,
            $limit,
            $days
        );

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $cacheResult;
        }

        $calculatedTime = new UTCDateTime((time() - ($days * 86400)) * 1000);

        $aggregateQuery =
            $attackerType && $typeId
                ? [
                    [
                        '$match' => [
                            "attackers.{$attackerType}" => $typeId,
                            "kill_time" => [
                                '$gte' => $calculatedTime,
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    ['$match' => [
                        "attackers.{$attackerType}" => $typeId,
                        "attackers.ship_id" => ['$nin' => [0, 670]],
                    ]],
                    [
                        '$group' => [
                            "_id" => [
                                'ship_id' => '$attackers.ship_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.ship_id',
                            "count" => ['$sum' => 1],
                        ],
                    ],
                    [
                        '$project' => [
                            "_id" => 0,
                            "count" => '$count',
                            "id" => '$_id',
                        ],
                    ],
                    ['$sort' => ["count" => -1]],
                    ['$limit' => $limit],
                ]
                : [
                    [
                        '$match' => [
                            "kill_time" => [
                                '$gte' => $calculatedTime,
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    ['$match' => [
                        "attackers.ship_id" => ['$nin' => [0, 670]],
                    ]],
                    [
                        '$group' => [
                            "_id" => [
                                'ship_id' => '$attackers.ship_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.ship_id',
                            "count" => ['$sum' => 1],
                        ],
                    ],
                    [
                        '$project' => [
                            "_id" => 0,
                            "count" => '$count',
                            "id" => '$_id',
                        ],
                    ],
                    ['$sort' => ["count" => -1]],
                    ['$limit' => $limit],
                ];

        $dataGenerator = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        $result = [];

        foreach ($dataGenerator as $ship) {
            $typeInfo = $this->typeIDs->findOne(
                ["type_id" => $ship["id"]],
                [
                    "projection" => [
                        "_id" => 0,
                        "last_modified" => 0,
                        "dogma_effects" => 0,
                        "dogma_attributes" => 0,
                    ],
                ]
            );

            $result[] = array_merge(
                ["count" => $ship["count"]],
                $typeInfo
            );
        }

        $this->cache->set($cacheKey, $result, $cacheTime);

        return $result;
    }

    public function topSystems(?string $attackerType = null, ?int $typeId = null, int $days = 30, int $limit = 10, int $cacheTime = 300): array
    {
        $cacheKey = $this->cache->generateKey(
            "top_systems",
            $attackerType,
            $typeId,
            $limit,
            $days
        );
        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $cacheResult;
        }

        $calculatedTime = new UTCDateTime((time() - ($days * 86400)) * 1000);

        $aggregateQuery =
            $attackerType && $typeId
                ? [
                    [
                        '$match' => [
                            "attackers.{$attackerType}" => $typeId,
                            "kill_time" => [
                                '$gte' => $calculatedTime,
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    ['$match' => ["attackers.{$attackerType}" => $typeId]],
                    [
                        '$group' => [
                            "_id" => [
                                'system_id' => '$system_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.system_id',
                            "count" => ['$sum' => 1],
                        ],
                    ],
                    [
                        '$project' => [
                            "_id" => 0,
                            "count" => '$count',
                            "id" => '$_id',
                        ],
                    ],
                    ['$sort' => ["count" => -1]],
                    ['$limit' => $limit],
                ]
                : [
                    [
                        '$match' => [
                            "kill_time" => [
                                '$gte' => $calculatedTime,
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    [
                        '$group' => [
                            "_id" => [
                                'system_id' => '$system_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.system_id',
                            "count" => ['$sum' => 1],
                        ],
                    ],
                    [
                        '$project' => [
                            "_id" => 0,
                            "count" => '$count',
                            "id" => '$_id',
                        ],
                    ],
                    ['$sort' => ["count" => -1]],
                    ['$limit' => $limit],
                ];

        $dataGenerator = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        $result = [];

        foreach ($dataGenerator as $system) {
            $systemInfo = $this->solarSystems->findOne(
                ["system_id" => $system["id"]],
                [
                    "projection" => [
                        "_id" => 0,
                        "last_modified" => 0,
                        "planets" => 0,
                        "stargates" => 0,
                        "stations" => 0,
                        "position" => 0,
                    ],
                ]
            );

            $result[] = array_merge(
                ["count" => $system["count"]],
                $systemInfo
            );
        }

        $this->cache->set($cacheKey, $result, $cacheTime);

        return $result;
    }

    public function topConstellations(?string $attackerType = null, ?int $typeId = null, int $days = 30, int $limit = 10, int $cacheTime = 300): array
    {
        $cacheKey = $this->cache->generateKey(
            "top_constellations",
            $attackerType,
            $typeId,
            $limit,
            $days
        );

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $cacheResult;
        }

        $calculatedTime = new UTCDateTime((time() - ($days * 86400)) * 1000);

        $matchFilter = [
            "kill_time" => ['$gte' => $calculatedTime],
        ];

        if ($attackerType && $typeId) {
            $matchFilter["attackers.{$attackerType}"] = $typeId;
        }

        $aggregateQuery = [
            ['$match' => $matchFilter],
            ['$unwind' => '$attackers'],
        ];

        if ($attackerType && $typeId) {
            $aggregateQuery[] = ['$match' => ["attackers.{$attackerType}" => $typeId]];
        }

        $aggregateQuery = array_merge($aggregateQuery, [
            [
                '$group' => [
                    "_id" => [
                        'system_id' => '$system_id',
                        'killmail_id' => '$killmail_id',
                    ],
                ],
            ],
            [
                '$group' => [
                    "_id" => '$_id.system_id',
                    "count" => ['$sum' => 1],
                ],
            ],
            [
                '$project' => [
                    "_id" => 0,
                    "count" => '$count',
                    "system_id" => '$_id',
                ],
            ],
        ]);

        // Fetch system to constellation mapping
        $systemsGenerator = $this->solarSystems->find([], ["projection" => ["system_id" => 1, "constellation_id" => 1]]);
        $systemToConstellation = [];
        foreach ($systemsGenerator as $system) {
            $systemToConstellation[$system['system_id']] = $system['constellation_id'];
        }

        $dataGenerator = $this->killmails->aggregate($aggregateQuery, [
            'allowDiskUse' => true,
            'maxTimeMS' => 30000,
        ]);

        $constellationCounts = [];

        foreach ($dataGenerator as $item) {
            $systemId = $item['system_id'];
            $count = $item['count'];
            $constellationId = $systemToConstellation[$systemId] ?? null;

            if ($constellationId !== null) {
                if (!isset($constellationCounts[$constellationId])) {
                    $constellationCounts[$constellationId] = 0;
                }
                $constellationCounts[$constellationId] += $count;
            }
        }

        // Sort constellations by count
        arsort($constellationCounts);
        $constellationCounts = array_slice($constellationCounts, 0, $limit, true);

        $result = [];
        foreach ($constellationCounts as $constellationId => $count) {
            $constellationInfo = $this->constellations->findOne(
                ['constellation_id' => $constellationId],
                [
                    'projection' => [
                        '_id' => 0,
                        'last_modified' => 0,
                        'systems' => 0,
                        'position' => 0,
                    ],
                ]
            );

            $result[] = array_merge(
                ['count' => $count],
                $constellationInfo
            );
        }

        $this->cache->set($cacheKey, $result, $cacheTime);

        return $result;
    }

    public function topRegions(?string $attackerType = null, ?int $typeId = null, int $days = 30, int $limit = 10, int $cacheTime = 300): array
    {
        $cacheKey = $this->cache->generateKey(
            'top_regions',
            $attackerType,
            $typeId,
            $limit,
            $days
        );

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $cacheResult;
        }

        $calculatedTime = new UTCDateTime((time() - ($days * 86400)) * 1000);

        $matchFilter = [
            "kill_time" => ['$gte' => $calculatedTime],
        ];

        if ($attackerType && $typeId) {
            $matchFilter["attackers.{$attackerType}"] = $typeId;
        }

        $aggregateQuery = [
            ['$match' => $matchFilter],
            ['$unwind' => '$attackers'],
        ];

        if ($attackerType && $typeId) {
            $aggregateQuery[] = ['$match' => ["attackers.{$attackerType}" => $typeId]];
        }

        $aggregateQuery = array_merge($aggregateQuery, [
            [
                '$group' => [
                    "_id" => [
                        'system_id' => '$system_id',
                        'killmail_id' => '$killmail_id',
                    ],
                ],
            ],
            [
                '$group' => [
                    "_id" => '$_id.system_id',
                    "count" => ['$sum' => 1],
                ],
            ],
            [
                '$project' => [
                    "_id" => 0,
                    "count" => '$count',
                    "system_id" => '$_id',
                ],
            ],
        ]);

        // Fetch system to region mapping
        $systemsGenerator = $this->solarSystems->find([], ["projection" => ["system_id" => 1, "region_id" => 1]]);
        $systemToRegion = [];
        foreach ($systemsGenerator as $system) {
            $systemToRegion[$system['system_id']] = $system['region_id'];
        }

        $dataGenerator = $this->killmails->aggregate($aggregateQuery, [
            'allowDiskUse' => true,
            'maxTimeMS' => 30000,
        ]);

        $regionCounts = [];

        foreach ($dataGenerator as $item) {
            $systemId = $item['system_id'];
            $count = $item['count'];
            $regionId = $systemToRegion[$systemId] ?? null;

            if ($regionId !== null) {
                if (!isset($regionCounts[$regionId])) {
                    $regionCounts[$regionId] = 0;
                }
                $regionCounts[$regionId] += $count;
            }
        }

        // Sort regions by count
        arsort($regionCounts);
        $regionCounts = array_slice($regionCounts, 0, $limit, true);

        $result = [];
        foreach ($regionCounts as $regionId => $count) {
            $regionInfo = $this->regions->findOne(
                ['region_id' => $regionId],
                [
                    'projection' => [
                        '_id' => 0,
                        'last_modified' => 0,
                        'constellations' => 0,
                    ],
                ]
            );

            $result[] = array_merge(
                ['count' => $count],
                $regionInfo
            );
        }

        $this->cache->set($cacheKey, $result, $cacheTime);

        return $result;
    }
}
