<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use Psr\Http\Message\ResponseInterface;

class Alliances extends Controller
{
    public function __construct(
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Characters $characters,
        protected \EK\Helpers\TopLists $topLists,
        protected \EK\Models\Killmails $killmails
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/alliances[/]", ["GET"])]
    public function all(): ResponseInterface
    {
        $alliances = $this->alliances
            ->find([], ["projection" => ["alliance_id" => 1]], 300)
            ->map(function ($alliance) {
                return $alliance["alliance_id"];
            });

        return $this->json($alliances->toArray(), 300);
    }

    #[RouteAttribute("/alliances/count[/]", ["GET"])]
    public function count(): ResponseInterface
    {
        return $this->json(["count" => $this->alliances->count()], 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}[/]", ["GET"])]
    public function alliance(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]]
        );
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        return $this->json($this->cleanupTimestamps($alliance->toArray()), 300);
    }

    #[RouteAttribute("/alliances[/]", ["POST"])]
    public function alliances(): ResponseInterface
    {
        $postData = json_validate($this->getBody())
            ? json_decode($this->getBody(), true)
            : [];
        if (empty($postData)) {
            return $this->json(["error" => "No data provided"], 300);
        }

        // Error if there are more than 1000 IDs
        if (count($postData) > 1000) {
            return $this->json(["error" => "Too many IDs provided"], 300);
        }

        // Find all the alliances in the post data
        $alliances = $this->alliances
            ->find(
                ["alliance_id" => ['$in' => $postData]],
                ["projection" => ["_id" => 0]],
                300
            )
            ->map(function ($alliance) {
                return $this->cleanupTimestamps($alliance);
            });

        return $this->json(
            $this->cleanupTimestamps($alliances->toArray()),
            300
        );
    }

    #[RouteAttribute("/alliances/{alliance_id}/killmails[/]", ["GET"])]
    public function killmails(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(["alliance_id" => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $killmails = $this->killmails
            ->aggregate(
                [
                    [
                        '$match' => [
                            '$or' => [
                                ["victim.alliance_id" => $alliance_id],
                                ["attackers.alliance_id" => $alliance_id],
                            ],
                        ],
                    ],
                    ['$project' => ["_id" => 0, "killmail_id" => 1]],
                ],
                [
                    "allowDiskUse" => true,
                    "maxTimeMS" => 60000,
                ],
                3600
            )
            ->map(function ($killmail) {
                return $killmail["killmail_id"];
            });

        return $this->json($killmails, 3600);
    }

    #[RouteAttribute("/alliances/{alliance_id}/killmails/count[/]", ["GET"])]
    public function killmailsCount(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(["alliance_id" => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $killCount = $this->killmails->count(
            ["attackers.alliance_id" => $alliance_id],
            []
        );
        $lossCount = $this->killmails->count(
            ["victim.alliance_id" => $alliance_id],
            []
        );

        return $this->json(
            ["kills" => $killCount, "losses" => $lossCount],
            300
        );
    }

    #[RouteAttribute("/alliances/{alliance_id}/killmails/latest[/]", ["GET"])]
    public function latestKillmails(int $alliance_id): ResponseInterface
    {
        $limit = (int) $this->getParam("limit", 1000);
        if ($limit > 1000 || $limit < 1) {
            return $this->json(
                ["error" => "Wrong limit", "range" => "1-1000"],
                300
            );
        }

        $alliance = $this->alliances->findOne(["alliance_id" => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $killmails = $this->killmails
            ->aggregate(
                [
                    [
                        '$match' => [
                            '$or' => [
                                [
                                    "victim.alliance_id" => $alliance_id,
                                ],
                                [
                                    "attackers.alliance_id" => $alliance_id,
                                ],
                            ],
                        ],
                    ],
                    ['$sort' => ["kill_time" => -1]],
                    ['$limit' => $limit],
                    ['$project' => ["_id" => 0, "killmail_id" => 1]],
                ],
                [],
                3600
            )
            ->map(function ($killmail) {
                return $killmail["killmail_id"];
            });

        return $this->json($killmails, 3600);
    }

    #[RouteAttribute("/alliances/{alliance_id}/members[/]", ["GET"])]
    public function members(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(["alliance_id" => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $members = $this->characters
            ->find(
                ["alliance_id" => $alliance_id],
                ["projection" => ["_id" => 0]],
                300
            )
            ->map(function ($member) {
                return $this->cleanupTimestamps($member);
            });

        return $this->json($members->toArray(), 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/members/characters[/]", ["GET"])]
    public function characters(int $alliance_id): ResponseInterface
    {
        return $this->members($alliance_id);
    }

    #[
        RouteAttribute("/alliances/{alliance_id}/members/corporations[/]", [
            "GET",
        ])
    ]
    public function corporations(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(["alliance_id" => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $members = $this->corporations
            ->find(
                ["alliance_id" => $alliance_id],
                ["projection" => ["_id" => 0]],
                300
            )
            ->map(function ($member) {
                return $this->cleanupTimestamps($member);
            });

        return $this->json($members->toArray(), 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/characters[/]", ["GET"])]
    public function topCharacters(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(["alliance_id" => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topCharacters = $this->topLists->topCharacters(
            "alliance_id",
            $alliance_id
        );

        return $this->json($topCharacters, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/corporations[/]", ["GET"])]
    public function topCorporations(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(["alliance_id" => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topCorporations = $this->topLists->topCorporations(
            "alliance_id",
            $alliance_id
        );

        return $this->json($topCorporations, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/ships[/]", ["GET"])]
    public function topShips(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(["alliance_id" => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topShips = $this->topLists->topShips("alliance_id", $alliance_id);

        return $this->json($topShips, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/systems[/]", ["GET"])]
    public function topSystems(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(["alliance_id" => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topSystems = $this->topLists->topSystems("alliance_id", $alliance_id);

        return $this->json($topSystems, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/regions[/]", ["GET"])]
    public function topRegions(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(["alliance_id" => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topRegions = $this->topLists->topRegions("alliance_id", $alliance_id);

        return $this->json($topRegions, 300);
    }
}
