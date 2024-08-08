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

    #[RouteAttribute("/stats/topcharacters/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"])]
    public function top10Characters(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topCharacters(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topcorporations/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"])]
    public function top10Corporations(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topCorporations(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topalliances/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"])]
    public function top10Alliances(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topAlliances(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topsolarsystems/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"])]
    public function top10Systems(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topSystems(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topregions/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"])]
    public function top10Regions(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topRegions(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/topships/{count:[0-9]+}[/{days:[0-9]+}]", ["GET"])]
    public function top10Ships(int $count = 10, int $days = 7): ResponseInterface
    {
        $days = $days === 0 ? $this->daysSinceEarlyDays : $days;
        $data = $this->topLists->topShips(
            days: $days,
            cacheTime: 3600,
            limit: $count
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/mostvaluablekills/{days:[0-9]+}[/{limit:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/stats/killcount/[/{days:[0-9]+}]", ["GET"])]
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
