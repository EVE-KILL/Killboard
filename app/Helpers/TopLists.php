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

        $data = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        foreach ($data as $key => $character) {
            $data[$key] = array_merge(
                ["count" => $character["count"]],
                $this->characters
                    ->findOne(
                        ["character_id" => $character["id"]],
                        [
                            "projection" => [
                                "_id" => 0,
                                "last_modified" => 0,
                                "history" => 0,
                                "description" => 0,
                            ],
                        ]
                    )
                    ->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, $cacheTime);

        return $data->toArray();
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

        $data = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        foreach ($data as $key => $corporation) {
            $data[$key] = array_merge(
                ["count" => $corporation["count"]],
                $this->corporations
                    ->findOne(
                        ["corporation_id" => $corporation["id"]],
                        [
                            "projection" => [
                                "_id" => 0,
                                "last_modified" => 0,
                                "history" => 0,
                                "description" => 0,
                            ],
                        ]
                    )
                    ->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, $cacheTime);

        return $data->toArray();
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

        $aggregateQuery =
            $attackerType && $typeId
                ? [
                    [
                        '$match' => [
                            "attackers.{$attackerType}" => $typeId,
                            "attackers.alliance_id" => ['$ne' => 0],
                            "kill_time" => [
                                '$gte' => new UTCDateTime(
                                    (time() - ($days * 86400)) * 1000
                                ),
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
                                '$gte' => new UTCDateTime(
                                    (time() - ($days * 86400)) * 1000
                                ),
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

        $data = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        foreach ($data as $key => $alliance) {
            $data[$key] = array_merge(
                ["count" => $alliance["count"]],
                $this->alliances
                    ->findOne(
                        ["alliance_id" => $alliance["id"]],
                        ["projection" => ["_id" => 0, "last_modified" => 0]]
                    )
                    ->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, $cacheTime);

        return $data->toArray();
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

        $aggregateQuery =
            $attackerType && $typeId
                ? [
                    [
                        '$match' => [
                            "attackers.{$attackerType}" => $typeId,
                            "kill_time" => [
                                '$gte' => new UTCDateTime(
                                    (time() - ($days * 86400)) * 1000
                                ),
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
                                '$gte' => new UTCDateTime(
                                    (time() - ($days * 86400)) * 1000
                                ),
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

        $data = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        foreach ($data as $key => $ship) {
            $data[$key] = array_merge(
                ["count" => $ship["count"]],
                $this->typeIDs
                    ->findOne(
                        ["type_id" => $ship["id"]],
                        [
                            "projection" => [
                                "_id" => 0,
                                "last_modified" => 0,
                                "dogma_effects" => 0,
                                "dogma_attributes" => 0,
                            ],
                        ]
                    )
                    ->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, $cacheTime);

        return $data->toArray();
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

        $aggregateQuery =
            $attackerType && $typeId
                ? [
                    [
                        '$match' => [
                            "attackers.{$attackerType}" => $typeId,
                            "kill_time" => [
                                '$gte' => new UTCDateTime(
                                    (time() - ($days * 86400)) * 1000
                                ),
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
                                '$gte' => new UTCDateTime(
                                    (time() - ($days * 86400)) * 1000
                                ),
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

        $data = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        foreach ($data as $key => $system) {
            $data[$key] = array_merge(
                ["count" => $system["count"]],
                $this->solarSystems
                    ->findOne(
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
                    )
                    ->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, $cacheTime);

        return $data->toArray();
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

    // Get the constellations with their systems
    $constellations = $this->constellations->find([])->toArray();
    $systemsToConstellations = [];

    foreach ($constellations as $constellation) {
        foreach ($constellation['systems'] as $systemId) {
            $systemsToConstellations[$systemId] = $constellation['constellation_id'];
        }
    }

    $aggregateQuery =
        $attackerType && $typeId
            ? [
                [
                    '$match' => [
                        "attackers.{$attackerType}" => $typeId,
                        "kill_time" => [
                            '$gte' => new UTCDateTime(
                                (time() - ($days * 86400)) * 1000
                            ),
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
                    '$addFields' => [
                        'constellation_id' => [
                            '$let' => [
                                'vars' => ['system_id' => '$_id'],
                                'in' => [
                                    '$arrayElemAt' => [
                                        ['$filter' => [
                                            'input' => array_map(
                                                fn($systemId) => [
                                                    '_id' => $systemId,
                                                    'constellation_id' => $systemsToConstellations[$systemId] ?? null
                                                ],
                                                array_keys($systemsToConstellations)
                                            ),
                                            'as' => 'item',
                                            'cond' => [
                                                '$eq' => ['$$item._id', '$$system_id']
                                            ]
                                        ]],
                                        0
                                    ],
                                ]
                            ],
                        ]
                    ],
                ],
                [
                    '$group' => [
                        "_id" => '$constellation_id.constellation_id',
                        "count" => ['$sum' => '$count'],
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
                            '$gte' => new UTCDateTime(
                                (time() - ($days * 86400)) * 1000
                            ),
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
                    '$addFields' => [
                        'constellation_id' => [
                            '$let' => [
                                'vars' => ['system_id' => '$_id'],
                                'in' => [
                                    '$arrayElemAt' => [
                                        ['$filter' => [
                                            'input' => array_map(
                                                fn($systemId) => [
                                                    '_id' => $systemId,
                                                    'constellation_id' => $systemsToConstellations[$systemId] ?? null
                                                ],
                                                array_keys($systemsToConstellations)
                                            ),
                                            'as' => 'item',
                                            'cond' => [
                                                '$eq' => ['$$item._id', '$$system_id']
                                            ]
                                        ]],
                                        0
                                    ],
                                ]
                            ],
                        ]
                    ],
                ],
                [
                    '$group' => [
                        "_id" => '$constellation_id.constellation_id',
                        "count" => ['$sum' => '$count'],
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

    $data = $this->killmails->aggregate($aggregateQuery, [
        'allowDiskUse' => true,
        'maxTimeMS' => 30000,
    ]);

    foreach ($data as $key => $constellation) {
        $data[$key] = array_merge(
            ['count' => $constellation['count']],
            $this->constellations
                ->findOne(
                    ['constellation_id' => $constellation['id']],
                    [
                        'projection' => [
                            '_id' => 0,
                            'last_modified' => 0,
                            'systems' => 0,
                            'position' => 0
                        ],
                    ]
                )
                ->toArray()
        );
    }

    $this->cache->set($cacheKey, $data, $cacheTime);

    return $data->toArray();
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

        $aggregateQuery =
            $attackerType && $typeId
                ? [
                    [
                        '$match' => [
                            "attackers.{$attackerType}" => $typeId,
                            "kill_time" => [
                                '$gte' => new UTCDateTime(
                                    (time() - ($days * 86400)) * 1000
                                ),
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    ['$match' => ["attackers.{$attackerType}" => $typeId]],
                    [
                        '$group' => [
                            "_id" => [
                                'region_id' => '$region_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.region_id',
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
                                '$gte' => new UTCDateTime(
                                    (time() - ($days * 86400)) * 1000
                                ),
                            ],
                        ],
                    ],
                    ['$unwind' => '$attackers'],
                    [
                        '$group' => [
                            "_id" => [
                                'region_id' => '$region_id',
                                'killmail_id' => '$killmail_id',
                            ],
                        ],
                    ],
                    [
                        '$group' => [
                            "_id" => '$_id.region_id',
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

        $data = $this->killmails->aggregate($aggregateQuery, [
            "allowDiskUse" => true,
            "maxTimeMS" => 30000,
        ]);

        foreach ($data as $key => $region) {
            $data[$key] = array_merge(
                ["count" => $region["count"]],
                $this->regions
                    ->findOne(
                        ["region_id" => $region["id"]],
                        ["projection" => [
                            "_id" => 0,
                            "last_modified" => 0,
                            "constellations" => 0,
                        ]]
                    )
                    ->toArray()
            );
        }

        $this->cache->set($cacheKey, $data, $cacheTime);

        return $data->toArray();
    }
}
