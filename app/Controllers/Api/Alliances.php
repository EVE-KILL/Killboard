<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Helpers\History;
use EK\Helpers\TopLists;
use EK\Models\Corporations;
use EK\Models\Characters;
use EK\Models\Alliances as ModelsAlliances;
use EK\Models\Killmails;
use EK\Cache\Cache;
use Psr\Http\Message\ResponseInterface;

class Alliances extends Controller
{
    public function __construct(
        protected ModelsAlliances $alliances,
        protected Corporations $corporations,
        protected Characters $characters,
        protected TopLists $topLists,
        protected Killmails $killmails,
        protected History $history,
        protected Cache $cache
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/alliances[/]", ["GET"], "List all alliances")]
    public function all(): ResponseInterface
    {
        $alliancesGenerator = $this->alliances
            ->find([], ["projection" => ["alliance_id" => 1]], 300, false);

        $alliances = [];
        foreach ($alliancesGenerator as $alliance) {
            $alliances[] = $alliance["alliance_id"];
        }

        return $this->json($alliances, 300);
    }

    #[RouteAttribute("/alliances/count[/]", ["GET"], "Return the amount of alliances")]
    public function count(): ResponseInterface
    {
        return $this->json(["count" => $this->alliances->aproximateCount()], 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}[/]", ["GET"], "Get alliance information")]
    public function alliance(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        return $this->json(
            $this->cleanupTimestamps($alliance),
            300
        );
    }

    #[RouteAttribute("/alliances[/]", ["POST"], "Get information for multiple alliances")]
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

        $alliancesGenerator = $this->alliances
            ->find(
                ["alliance_id" => ['$in' => $postData]],
                ["projection" => ["_id" => 0]],
                300,
                false
            );

        $alliances = [];
        foreach ($alliancesGenerator as $alliance) {
            $alliances[] = $this->cleanupTimestamps($alliance);
        }

        return $this->json($alliances, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/killmails[/]", ["GET"], "Get killmails for an alliance")]
    public function killmails(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $killmailsGenerator = $this->killmails->aggregate(
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
            [],
            3600
        );

        $killmails = [];
        foreach ($killmailsGenerator as $killmail) {
            $killmails[] = $killmail["killmail_id"];
        }

        $killmails = $this->cleanupTimestamps($killmails);

        return $this->json($killmails, 3600);
    }

    #[RouteAttribute("/alliances/{alliance_id}/killmails/count[/]", ["GET"], "Get the count of killmails for an alliance")]
    public function killmailsCount(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $killCount = $this->killmails->count(
            ["attackers.alliance_id" => $alliance_id]
        );
        $lossCount = $this->killmails->count(
            ["victim.alliance_id" => $alliance_id]
        );

        return $this->json(
            ["kills" => $killCount, "losses" => $lossCount],
            300
        );
    }

    #[RouteAttribute("/alliances/{alliance_id}/killmails/latest[/]", ["GET"], "Get the latest killmails for an alliance")]
    public function latestKillmails(int $alliance_id): ResponseInterface
    {
        $limit = (int) $this->getParam("limit", 1000);
        if ($limit > 1000 || $limit < 1) {
            return $this->json(
                ["error" => "Wrong limit", "range" => "1-1000"],
                300
            );
        }

        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $killmailsGenerator = $this->killmails->aggregate(
            [
                [
                    '$match' => [
                        '$or' => [
                            ["victim.alliance_id" => $alliance_id],
                            ["attackers.alliance_id" => $alliance_id],
                        ],
                    ],
                ],
                ['$sort' => ["kill_time" => -1]],
                ['$limit' => $limit],
                ['$project' => ["_id" => 0, "killmail_id" => 1]],
            ],
            [],
            3600
        );

        $killmails = [];
        foreach ($killmailsGenerator as $killmail) {
            $killmails[] = $killmail["killmail_id"];
        }

        $killmails = $this->cleanupTimestamps($killmails);

        return $this->json($killmails, 3600);
    }

    #[RouteAttribute("/alliances/{alliance_id}/members[/]", ["GET"], "Get members of an alliance")]
    public function members(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $membersGenerator = $this->characters->find(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        $members = [];
        foreach ($membersGenerator as $member) {
            $members[] = $this->cleanupTimestamps($member);
        }

        return $this->json($members, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/members/characters[/]", ["GET"], "Get characters of an alliance")]
    public function characters(int $alliance_id): ResponseInterface
    {
        return $this->members($alliance_id);
    }

    #[RouteAttribute("/alliances/{alliance_id}/members/corporations[/]", ["GET"], "Get corporations of an alliance")]
    public function corporations(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $membersGenerator = $this->corporations->find(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        $members = [];
        foreach ($membersGenerator as $member) {
            $members[] = $this->cleanupTimestamps($member);
        }

        return $this->json($members, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/characters[/]", ["GET"], "Get top characters of an alliance")]
    public function topCharacters(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topCharacters = $this->topLists->topCharacters(
            "alliance_id",
            $alliance_id
        );

        $topCharacters = $this->cleanupTimestamps($topCharacters);

        return $this->json($topCharacters, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/solo[/]", ["GET"], "Get the top solo characters of an alliance")]
    public function topSolo(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topSolo = $this->topLists->topSolo(
            "alliance_id",
            $alliance_id
        );

        $topSolo = $this->cleanupTimestamps($topSolo);

        return $this->json($topSolo, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/corporations[/]", ["GET"], "Get top corporations of an alliance")]
    public function topCorporations(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topCorporations = $this->topLists->topCorporations(
            "alliance_id",
            $alliance_id
        );

        $topCorporations = $this->cleanupTimestamps($topCorporations);

        return $this->json($topCorporations, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/ships[/]", ["GET"], "Get top ships of an alliance")]
    public function topShips(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topShips = $this->topLists->topShips(
            "alliance_id",
            $alliance_id
        );

        $topShips = $this->cleanupTimestamps($topShips);

        return $this->json($topShips, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/systems[/]", ["GET"], "Get top systems of an alliance")]
    public function topSystems(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topSystems = $this->topLists->topSystems(
            "alliance_id",
            $alliance_id
        );

        $topSystems = $this->cleanupTimestamps($topSystems);

        return $this->json($topSystems, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/top/regions[/]", ["GET"], "Get top regions of an alliance")]
    public function topRegions(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $topRegions = $this->topLists->topRegions(
            "alliance_id",
            $alliance_id
        );

        $topRegions = $this->cleanupTimestamps($topRegions);

        return $this->json($topRegions, 300);
    }

    #[RouteAttribute("/alliances/{alliance_id}/alliancehistory[/]", ["GET"], "Get the alliance history of an alliance")]
    public function allianceHistory(int $alliance_id): ResponseInterface
    {
        return $this->json(['down' => true]);
        $alliance = $this->alliances->findOne(
            ["alliance_id" => $alliance_id],
            ["projection" => ["_id" => 0]],
            300,
            false
        );

        if (empty($alliance)) {
            return $this->json(["error" => "Alliance not found"], 300);
        }

        $allianceHistory = $this->history->getFullAllianceHistory($alliance_id);

        $this->alliances->update(
            ["alliance_id" => $alliance_id],
            ['$set' => ["history" => $allianceHistory]]
        );

        return $this->json($allianceHistory, 300);
    }
}
