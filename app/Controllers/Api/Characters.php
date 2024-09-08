<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Helpers\History;
use EK\Helpers\Stats;
use EK\Helpers\TopLists;
use EK\Models\Characters as ModelsCharacters;
use EK\Models\Killmails;
use Psr\Http\Message\ResponseInterface;

class Characters extends Controller
{
    public function __construct(
        protected ModelsCharacters $characters,
        protected Killmails $killmails,
        protected TopLists $topLists,
        protected Cache $cache,
        protected History $history,
        protected Stats $stats
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/characters[/page/{page:[0-9]+}]", ["GET"], "Get all characters")]
    public function all(int $page = 1): ResponseInterface
    {
        $limit = 10000;
        $skip = ($page - 1) * $limit;

        $cacheKey = "characters.all.$page";
        if ($this->cache->exists($cacheKey)) {
            return $this->json(
                $this->cache->get($cacheKey),
                $this->cache->getTTL($cacheKey)
            );
        }

        $characters = $this->characters
            ->find([], ['limit' => $limit, 'skip' => $skip, 'sort' => ['character_id' => 1], "projection" => ["character_id" => 1]], 300)
            ->map(function ($character) {
                return $character["character_id"];
            });

        $this->cache->set($cacheKey, $characters->toArray(), 3600);

        return $this->json($characters->toArray(), 3600);
    }

    #[RouteAttribute("/characters/count[/]", ["GET"], "Get the amount of characters")]
    public function count(): ResponseInterface
    {
        return $this->json(["count" => $this->characters->aproximateCount()], 300);
    }

    #[RouteAttribute("/characters/{character_id:[0-9]+}[/]", ["GET"], "Get a character by ID")]
    public function character(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(
            ["character_id" => $character_id],
            ["projection" => ["_id" => 0, 'error' => 0]]
        );
        if ($character->isEmpty()) {
            return $this->json(["error" => "Character not found"], 300);
        }

        return $this->json(
            $this->cleanupTimestamps($character->toArray()),
            300
        );
    }

    #[RouteAttribute("/characters/{character_id:[0-9]+}/shortstats[/{days:[0-9]+}]", ["GET"], "Get the stats of a character")]
    public function shortStats(int $character_id, int $days = 0): ResponseInterface
    {
        $cacheKey = "characters.stats.$character_id.$days";
        if ($this->cache->exists($cacheKey)) {
            return $this->json(
                $this->cache->get($cacheKey),
                $this->cache->getTTL($cacheKey)
            );
        }

        $stats = $this->stats->calculateShortStats("character_id", $character_id, $days);

        $this->cache->set($cacheKey, $stats, 3600);
        return $this->json($stats, 300);
    }

    #[RouteAttribute("/characters/{character_id:[0-9]+}/stats[/{days:[0-9]+}]", ["GET"], "Get the stats of a character")]
    public function stats(int $character_id, int $days = 0): ResponseInterface
    {
        $cacheKey = "characters.stats.$character_id.$days";
        if ($this->cache->exists($cacheKey)) {
            return $this->json(
                $this->cache->get($cacheKey),
                $this->cache->getTTL($cacheKey)
            );
        }

        $stats = $this->stats->calculateFullStats("character_id", $character_id, $days);

        $this->cache->set($cacheKey, $stats, 3600);
        return $this->json($stats, 300);
    }

    #[RouteAttribute("/characters[/]", ["POST"], "Get multiple characters by ID")]
    public function characters(): ResponseInterface
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

        $characters = $this->characters
            ->find(
                ["character_id" => ['$in' => $postData]],
                ["projection" => ["_id" => 0, 'error' => 0]],
                300
            )
            ->map(function ($character) {
                return $this->cleanupTimestamps($character);
            });

        return $this->json($characters->toArray(), 300);
    }

    #[RouteAttribute("/characters/{character_id:[0-9]+}/corporationhistory[/]", ["GET"], "Get the corporation history of a character")]
    public function corporationHistory(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne([
            "character_id" => $character_id,
        ]);
        if ($character->isEmpty()) {
            return $this->json(["error" => "Character not found"], 300);
        }

        $corporationHistory = $this->history->generateCorporationHistory($character_id);

        $this->characters->collection->updateOne(
            ["character_id" => $character_id],
            ['$set' => ["history" => $corporationHistory]]
        );

        return $this->json($corporationHistory, 3600);
    }


    #[RouteAttribute("/characters/{character_id:[0-9]+}/killmails[/]", ["GET"], "Get all killmails of a character")]
    public function killmails(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne([
            "character_id" => $character_id,
        ]);
        if ($character->isEmpty()) {
            return $this->json(["error" => "Character not found"], 300);
        }

        $killmails = $this->killmails
            ->aggregate(
                [
                    [
                        '$match' => [
                            '$or' => [
                                ["victim.character_id" => $character_id],
                                ["attackers.character_id" => $character_id],
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

    #[RouteAttribute("/characters/{character_id:[0-9]+}/killmails/count[/]", ["GET"], "Get the amount of killmails of a character")]
    public function killmailsCount(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne([
            "character_id" => $character_id,
        ]);
        if ($character->isEmpty()) {
            return $this->json(["error" => "Character not found"], 300);
        }

        $killCount = $this->killmails->count(
            ["attackers.character_id" => $character_id],
            []
        );
        $lossCount = $this->killmails->count(
            ["victim.character_id" => $character_id],
            []
        );

        return $this->json(
            ["kills" => $killCount, "losses" => $lossCount],
            300
        );
    }

    #[RouteAttribute("/characters/{character_id:[0-9]+}/killmails/latest[/]", ["GET"], "Get the latest killmails of a character")]
    public function latestKillmails(int $character_id): ResponseInterface
    {
        $limit = (int) $this->getParam("limit", 1000);
        if ($limit > 1000 || $limit < 1) {
            return $this->json(
                ["error" => "Wrong limit", "range" => "1-1000"],
                300
            );
        }

        $character = $this->characters->findOne([
            "character_id" => $character_id,
        ]);
        if ($character->isEmpty()) {
            return $this->json(["error" => "Character not found"], 300);
        }

        $killmails = $this->killmails
            ->aggregate(
                [
                    [
                        '$match' => [
                            '$or' => [
                                ["victim.character_id" => $character_id],
                                ["attackers.character_id" => $character_id],
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

    #[RouteAttribute("/characters/{character_id:[0-9]+}/top/ships[/]", ["GET"], "Get the top ships of a character")]
    public function topShips(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne([
            "character_id" => $character_id,
        ]);
        if ($character->isEmpty()) {
            return $this->json(["error" => "Character not found"], 300);
        }

        $topShips = $this->topLists->topShips("character_id", $character_id);

        return $this->json($topShips, 300);
    }

    #[RouteAttribute("/characters/{character_id:[0-9]+}/top/systems[/]", ["GET"], "Get the top systems of a character")]
    public function topSystems(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne([
            "character_id" => $character_id,
        ]);
        if ($character->isEmpty()) {
            return $this->json(["error" => "Character not found"], 300);
        }

        $topSystems = $this->topLists->topSystems(
            "character_id",
            $character_id
        );

        return $this->json($topSystems, 300);
    }

    #[RouteAttribute("/characters/{character_id:[0-9]+}/top/regions[/]", ["GET"], "Get the top regions of a character")]
    public function topRegions(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne([
            "character_id" => $character_id,
        ]);
        if ($character->isEmpty()) {
            return $this->json(["error" => "Character not found"], 300);
        }

        $topRegions = $this->topLists->topRegions(
            "character_id",
            $character_id
        );

        return $this->json($topRegions, 300);
    }
}
