<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Helpers\Battle;
use Psr\Http\Message\ResponseInterface;

class Killmail extends Controller
{
    public function __construct(
        protected \EK\Models\Killmails $killmails,
        protected \EK\Models\KillmailsESI $killmailsESI,
        protected Battle $battleHelper,
        protected Cache $cache
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/killmail/count[/]", ["GET"], "Get the count of all killmails")]
    public function count(): ResponseInterface
    {
        return $this->json([
            "count" => $this->killmails->count(),
        ]);
    }

    #[RouteAttribute("/killmail/{killmail_id:[0-9]+}[/]", ["GET"], "Get a killmail by ID")]
    public function killmail(int $killmail_id): ResponseInterface
    {
        $killmail = $this->killmails->findOneOrNull(
            ["killmail_id" => $killmail_id],
            ["projection" => ["_id" => 0]]
        );

        if ($killmail === null) {
            return $this->json(
                [
                    "error" => "Killmail not found",
                ],
                300
            );
        }

        return $this->json($this->cleanupTimestamps($killmail->toArray()));
    }

    #[RouteAttribute("/killmail/esi/{killmail_id:[0-9]+}[/]", ["GET"], "Get esi killmail by ID")]
    public function esi(int $killmail_id): ResponseInterface
    {
        $killmail = $this->killmailsESI->findOneOrNull(
            ["killmail_id" => $killmail_id],
            ["projection" => ["_id" => 0, "killmail_time_str" => 0]]
        );
        if ($killmail === null) {
            return $this->json(
                [
                    "error" => "Killmail not found",
                ],
                300
            );
        }

        return $this->json($this->cleanupTimestamps($killmail->toArray()));
    }

    #[RouteAttribute("/killmail/{killmail_id:[0-9]+}/inbattle[/]", ["GET"], "Check if a killmail is in a battle")]
    public function inBattle(int $killmail_id): ResponseInterface
    {
        $cacheKey = $this->cache->generateKey("inBattle", $killmail_id);
        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $this->json($cacheResult);
        }

        $killmailInBattle = $this->battleHelper->isKillInBattle($killmail_id);
        if ($killmailInBattle === null) {
            return $this->json(["error" => "Killmail not found"]);
        }

        if ($killmailInBattle === false) {
            $this->cache->set($cacheKey, [false], 300);
            return $this->json([false]);
        }

        $this->cache->set($cacheKey, [true], 300);
        return $this->json([true]);
    }

    #[RouteAttribute("/killmail[/]", ["POST"], "Get multiple killmails by ID")]
    public function killmails(): ResponseInterface
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

        $killmails = $this->killmails
            ->find(
                ["killmail_id" => ['$in' => $postData]],
                ["projection" => ["_id" => 0]],
                300
            )
            ->map(function ($killmail) {
                return $this->cleanupTimestamps($killmail);
            });

        return $this->json($killmails->toArray(), 300);
    }
}
