<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Helpers\TopLists;
use EK\Models\Killmails;
use Psr\Http\Message\ResponseInterface;

class Stats extends Controller
{
    protected int $daysSinceEarlyDays;
    public function __construct(
        protected TopLists $topLists,
        protected Killmails $killmails
    ) {
        parent::__construct();

        // This is the amount of days since 2007-12-05, the first killmail in the database
        $this->daysSinceEarlyDays = ceil(
            abs(strtotime("2007-12-05") - time()) / 86400
        );
    }

    #[RouteAttribute("/stats/top10characters[/{all_time:[0-1]}]", ["GET"])]
    public function top10Characters(int $all_time = 0): ResponseInterface
    {
        $data = $this->topLists->topCharacters(
            days: $all_time ? $this->daysSinceEarlyDays : 7,
            cacheTime: 3600
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/top10corporations[/{all_time:[0-1]}]", ["GET"])]
    public function top10Corporations(int $all_time = 0): ResponseInterface
    {
        $data = $this->topLists->topCorporations(
            days: $all_time ? $this->daysSinceEarlyDays : 7,
            cacheTime: 3600
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/top10alliances[/{all_time:[0-1]}]", ["GET"])]
    public function top10Alliances(int $all_time = 0): ResponseInterface
    {
        $data = $this->topLists->topAlliances(
            days: $all_time ? $this->daysSinceEarlyDays : 7,
            cacheTime: 3600
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/top10solarsystems[/{all_time:[0-1]}]", ["GET"])]
    public function top10Systems(int $all_time = 0): ResponseInterface
    {
        $data = $this->topLists->topSystems(
            days: $all_time ? $this->daysSinceEarlyDays : 7,
            cacheTime: 3600
        );
        return $this->json($data, 3600);
    }

    #[RouteAttribute("/stats/top10regions[/{all_time:[0-1]}]", ["GET"])]
    public function top10Regions(int $all_time = 0): ResponseInterface
    {
        $data = $this->topLists->topRegions(
            days: $all_time ? $this->daysSinceEarlyDays : 7,
            cacheTime: 3600
        );
        return $this->json($data, 3600);
    }

    #[
        RouteAttribute("/stats/mostvaluablekillslast7days[/{limit:[0-9]+}]", [
            "GET",
        ])
    ]
    public function mostValuableKillsLast7Days(
        int $limit = 6
    ): ResponseInterface {
        $kills = $this->killmails->find(
            [
                "kill_time" => [
                    '$gte' => new \MongoDB\BSON\UTCDateTime(
                        (time() - 604800) * 1000
                    ),
                ],
            ],
            [
                "projection" => ["_id" => 0],
                "sort" => ["total_value" => -1],
                "limit" => $limit,
            ]
        );

        return $this->json($kills->toArray(), 300);
    }

    #[RouteAttribute("/stats/sevendaykillcount[/]", ["GET"])]
    public function sevenDayKillCount(): ResponseInterface
    {
        $kills = $this->killmails->count([
            "kill_time" => [
                '$gte' => new \MongoDB\BSON\UTCDateTime(
                    (time() - 604800) * 1000
                ),
            ],
        ]);

        return $this->json(["count" => $kills], 300);
    }
}
