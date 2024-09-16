<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Helpers\TopLists;
use EK\Models\Characters;
use EK\Models\Killmails;
use Psr\Http\Message\ResponseInterface;

class Stats extends Controller
{
    protected int $daysSinceEarlyDays;
    public function __construct(
        protected TopLists $topLists,
        protected Killmails $killmails,
        protected Characters $characters,
        protected Cache $cache
    ) {
        parent::__construct();

        // This is the amount of days since 2007-12-05, the first killmail in the database
        $this->daysSinceEarlyDays = ceil(
            abs(strtotime("2007-12-05") - time()) / 86400
        );
    }

    #[RouteAttribute("/stats/topcharacters/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"], "Get top characters")]
    public function topCharacters(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topCharacters(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topcorporations/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"], "Get top corporations")]
    public function topCorporations(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topCorporations(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topalliances/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"], "Get top alliances")]
    public function topAlliances(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topAlliances(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topsolarsystems/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"], "Get top solar systems")]
    public function topSystems(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topSystems(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topconstellations/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"], "Get top constellations")]
    public function topConstellations(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topConstellations(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topregions/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"], "Get top regions")]
    public function topRegions(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topRegions(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topships/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"], "Get top ships")]
    public function topShips(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topShips(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/mostvaluablekills/{days:[0-9]+}[/{limit:[0-9]+}]", ["GET"], "Get most valuable kills")]
    public function mostValuableKills(int $days = 7, int $limit = 10): ResponseInterface
    {
        $cacheKey = $this->cache->generateKey(
            "most_valuable_kills",
            $limit,
            $days
        );

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $this->json($cacheResult, 300);
        }

        $kills = $this->killmails->find(
            [
                "kill_time" => [
                    '$gte' => new \MongoDB\BSON\UTCDateTime((time() - (((60 * 60) * 24) * $days)) * 1000),
                ],
            ],
            [
                "projection" => ["_id" => 0],
                "sort" => ["total_value" => -1],
                "limit" => $limit,
            ]
        );

        $this->cache->set($cacheKey, iterator_to_array($kills), 300);
        return $this->json(iterator_to_array($kills), 300);
    }

    #[RouteAttribute("/stats/mostvaluablestructures/{days:[0-9]+}[/{limit:[0-9]+}]", ["GET"], "Get most valuable structure kills")]
    public function mostValuableStructures(int $days = 7, int $limit = 10): ResponseInterface
    {
        $cacheKey = $this->cache->generateKey(
            "most_valuable_structure_kills",
            $limit,
            $days
        );

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $this->json($cacheResult, 300);
        }

        $kills = $this->killmails->find(
            [
                "kill_time" => [
                    '$gte' => new \MongoDB\BSON\UTCDateTime((time() - (((60 * 60) * 24) * $days)) * 1000),
                ],
                'victim.ship_group_id' => ['$in' => [
                    1657, 1406, 1404, 1408, 2017, 2016
                ]]
            ],
            [
                "projection" => ["_id" => 0],
                "sort" => ["total_value" => -1],
                "limit" => $limit,
            ]
        );

        $this->cache->set($cacheKey, iterator_to_array($kills), 300);
        return $this->json(iterator_to_array($kills), 300);
    }

    #[RouteAttribute("/stats/mostvaluableships/{days:[0-9]+}[/{limit:[0-9]+}]", ["GET"], "Get most valuable ship kills")]
    public function mostValuableShips(int $days = 7, int $limit = 10): ResponseInterface
    {
        $cacheKey = $this->cache->generateKey(
            "most_valuable_ship_kills",
            $limit,
            $days
        );

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $this->json($cacheResult, 300);
        }

        $kills = $this->killmails->find(
            [
                "kill_time" => [
                    '$gte' => new \MongoDB\BSON\UTCDateTime((time() - (((60 * 60) * 24) * $days)) * 1000),
                ],
                'victim.ship_group_id' => ['$in' => [
                    547,485,513,902,941,30,659,419,27,29,26,420,25,28,463,237,31,324,898,906,540,830,893,543,541,833,358,894,831,832,900,834,380,963,1305
                ]],
            ],
            [
                "projection" => ["_id" => 0],
                "sort" => ["total_value" => -1],
                "limit" => $limit,
            ]
        );

        $this->cache->set($cacheKey, iterator_to_array($kills), 300);
        return $this->json(iterator_to_array($kills), 300);
    }

    #[RouteAttribute("/stats/killcount/[/{days:[0-9]+}]", ["GET"], "Get kill count")]
    public function sevenDayKillCount(int $days = 7): ResponseInterface
    {
        $cacheKey = $this->cache->generateKey("kill_count", $days);

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $this->json($cacheResult, 300);
        }

        $kills = $this->killmails->count([
            "kill_time" => [
                '$gte' => new \MongoDB\BSON\UTCDateTime((time() - (((60 * 60) * 24) * $days)) * 1000),
            ],
        ]);

        $this->cache->set($cacheKey, ["count" => $kills], 300);
        return $this->json(["count" => $kills], 300);
    }

    #[RouteAttribute('/stats/newcharacters', ['GET'], 'Get the count of new characters created grouped by year, month, and day')]
    public function countNewCharactersYearlyMonthlyDaily(): ResponseInterface
    {
        $cacheKey = $this->cache->generateKey('new_characters_count');

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $this->json($cacheResult, 3600);
        }

        // Define the threshold date (January 1, 2003)
        $thresholdDate = new \MongoDB\BSON\UTCDateTime(strtotime('2003-01-01T00:00:00Z') * 1000);

        // Aggregation pipeline
        $aggregationResults = $this->characters->aggregate([
            [
                '$match' => [
                    'birthday' => [
                        '$gte' => $thresholdDate,  // Only include documents where birthday is on or after January 1, 2003
                    ],
                ],
            ],
            [
                '$group' => [
                    '_id' => [
                        'year' => [
                            '$year' => '$birthday',
                        ],
                        'month' => [
                            '$month' => '$birthday',
                        ],
                        'day' => [
                            '$dayOfMonth' => '$birthday',
                        ],
                    ],
                    'count' => [
                        '$sum' => 1,
                    ],
                ],
            ],
            [
                '$sort' => [
                    '_id.year' => 1,
                    '_id.month' => 1,
                    '_id.day' => 1,
                ],
            ],
        ], [
            'hint' => [
                'birthday' => -1,
            ],
        ]);

        // Post-processing the results to fit the desired structure
        $data = [];

        foreach ($aggregationResults as $result) {
            $year = $result['_id']['year'];
            $month = $result['_id']['month'];
            $day = $result['_id']['day'];
            $count = $result['count'];

            // Initialize the year if it doesn't exist
            if (!isset($data[$year])) {
                $data[$year] = [
                    'year' => $year,
                    'count' => 0,  // Initialize total count for the year
                    'months' => [],
                ];
            }

            // Initialize the month if it doesn't exist
            if (!isset($data[$year]['months'][$month])) {
                $data[$year]['months'][$month] = [
                    'count' => 0,  // Initialize total count for the month
                    'days' => [],
                ];
            }

            // Add the count for the day
            $data[$year]['months'][$month]['days'][$day] = $count;

            // Update the counts for the month and year
            $data[$year]['months'][$month]['count'] += $count;
            $data[$year]['count'] += $count;
        }

        // Re-index the array to make it a list of objects, rather than an associative array
        $data = array_values($data);

        $this->cache->set($cacheKey, $data, 3600);

        return $this->json($data, 3600);
    }
}
