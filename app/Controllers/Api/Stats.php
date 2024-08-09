<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Helpers\TopLists;
use EK\Models\Killmails;
use Psr\Http\Message\ResponseInterface;

class Stats extends Controller
{
    protected int $daysSinceEarlyDays;
    public function __construct(
        protected TopLists $topLists,
        protected Killmails $killmails,
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
    public function mostValuableKillsLast7Days(int $days = 7, int $limit = 6): ResponseInterface {
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

        $this->cache->set($cacheKey, $kills->toArray(), 300);
        return $this->json($kills->toArray(), 300);
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
}
