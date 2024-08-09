<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Helpers\Battle;
use EK\Models\Battles as BattlesModel;
use Psr\Http\Message\ResponseInterface;

class Battles extends Controller
{
    public function __construct(
        protected BattlesModel $battles,
        protected Battle $battleHelper,
        protected Cache $cache
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/battles[/]", ["GET"], "Get all battles")]
    public function all(): ResponseInterface
    {
        $battles = $this->battles
            ->find(
                [],
                [
                    "hint" => "battle_id",
                    "projection" => ["_id" => 0, "battle_id" => 1],
                ]
            )
            ->map(fn($battle) => $battle["battle_id"]);

        return $this->json($battles);
    }

    #[RouteAttribute("/battles/{id:[a-zA-Z0-9]+}[/]", ["GET"], "Get a battle by ID")]
    public function get(string $id): ResponseInterface
    {
        $battle = $this->battles
            ->findOne(
                ["battle_id" => $id],
                ["hint" => "battle_id", "projection" => ["_id" => 0]]
            )
            ->toArray();

        return $this->json($this->cleanupTimestamps($battle));
    }

    #[RouteAttribute("/battles/killmail/{killmailId:[0-9]+}[/]", ["GET"], "Check if a killmail is in a battle")]
    public function isKillmailInBattle(int $killmailId): ResponseInterface
    {
        $killmailInBattle = $this->battleHelper->isKillInBattle($killmailId);
        if ($killmailInBattle === null) {
            return $this->json(["error" => "Killmail not found"]);
        }

        if ($killmailInBattle === false) {
            return $this->json(["error" => "Killmail not in a battle"]);
        }

        $cacheKey = $this->cache->generateKey("battle", $killmailId);
        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $this->json($cacheResult);
        }

        // Get battle data
        $battleData = $this->battleHelper->getBattleData($killmailId);
        $battleData = $this->cleanupTimestamps($battleData);

        $this->cache->set($cacheKey, $battleData, 60);

        return $this->json($battleData);
    }
}
