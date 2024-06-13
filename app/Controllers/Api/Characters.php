<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Fetchers\CorporationHistory;
use EK\Http\Twig\Twig;
use Psr\Http\Message\ResponseInterface;

class Characters extends Controller
{
    public function __construct(
        protected \EK\Models\Characters $characters,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Jobs\updateCorporation $updateCorporation,
        protected \EK\ESI\Corporations $corporationESI,
        protected \EK\Helpers\TopLists $topLists,
        protected \EK\Cache\Cache $cache,
        protected \EK\Models\Killmails $killmails,
        protected CorporationHistory $corporationHistoryFetcher,
    ) {
        parent::__construct();
    }

    #[RouteAttribute('/characters[/]', ['GET'])]
    public function all(): ResponseInterface
    {
        $cacheKey = 'characters.all';
        if ($this->cache->exists($cacheKey)) {
            return $this->json($this->cache->get($cacheKey), $this->cache->getTTL($cacheKey));
        }

        $characters = $this->characters->find([], ['projection' => ['character_id' => 1]], 300)->map(function ($character) {
            return $character['character_id'];
        });

        $this->cache->set($cacheKey, $characters->toArray(), 3600);

        return $this->json($characters->toArray(), 3600);
    }

    #[RouteAttribute('/characters/count[/]', ['GET'])]
    public function count(): ResponseInterface
    {
        return $this->json(['count' => $this->characters->count()], 300);
    }

    #[RouteAttribute('/characters/{character_id:[0-9]+}[/]', ['GET'])]
    public function character(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(['character_id' => $character_id], ['projection' => ['_id' => 0]]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        return $this->json($this->cleanupTimestamps($character->toArray()), 300);
    }

    #[RouteAttribute('/characters[/]', ['POST'])]
    public function characters(): ResponseInterface
    {
        $postData = json_validate($this->getBody()) ? json_decode($this->getBody(), true) : [];
        if (empty($postData)) {
            return $this->json(['error' => 'No data provided'], 300);
        }

        // Error if there are more than 1000 IDs
        if (count($postData) > 1000) {
            return $this->json(['error' => 'Too many IDs provided'], 300);
        }

        $characters = $this->characters->find(['character_id' => ['$in' => $postData]], ['projection' => ['_id' => 0]], 300)->map(function ($character) {
            return $this->cleanupTimestamps($character);
        });

        return $this->json($characters->toArray(), 300);
    }

    #[RouteAttribute('/characters/{character_id:[0-9]+}/corporationhistory[/]', ['GET'])]
    public function corporationHistory(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(['character_id' => $character_id]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        $response = $this->corporationHistoryFetcher->fetch('/latest/characters/' . $character_id . '/corporationhistory', cacheTime: 60 * 60 * 24 * 7);
        $corpHistoryData = json_validate($response['body']) ? json_decode($response['body'], true) : [];
        $corpHistoryData = array_reverse($corpHistoryData);

        // Extract all corporation ids
        $corporationIds = array_column($corpHistoryData, 'corporation_id');

        // Fetch all corporation data at once
        $corporationsData = $this->corporations->find(['corporation_id' => ['$in' => $corporationIds]], ['projection' => ['_id' => 0]], 300)->toArray();

        // Convert to associative array
        $corporationsDataAssoc = [];
        foreach ($corporationsData as $corporationData) {
            $corporationsDataAssoc[$corporationData['corporation_id']] = $corporationData;
        }

        $corporationHistory = [];
        for ($i = 0; $i < count($corpHistoryData); $i++) {
            $history = $corpHistoryData[$i];
            $corpData = $corporationsDataAssoc[$history['corporation_id']] ?? null;
            if ($corpData === null) {
                $corpData = $this->corporationESI->getCorporationInfo($history['corporation_id']);
            }
            $joinDate = new \DateTime($history['start_date']);

            $data = [
                'corporation_id' => $history['corporation_id'],
                'join_date' => $joinDate->format('Y-m-d H:i:s')
            ];

            // If there is a next element, set the leave_date to the join_date of the next element
            if (isset($corpHistoryData[$i + 1])) {
                $nextHistory = $corpHistoryData[$i + 1];
                $nextJoinDate = new \DateTime($nextHistory['start_date']);
                $data['leave_date'] = $nextJoinDate->format('Y-m-d H:i:s');
            }

            $corporationHistory[] = $this->cleanupTimestamps(array_merge($data, $corpData));
        }

        return $this->json($corporationHistory);

        // Lets update the character with the latest corporation history
        $this->characters->collection->updateOne(
            ['character_id' => $character_id],
            ['$set' => ['corporation_history' => $corporationHistory]],
        );
    }

    #[RouteAttribute('/characters/{character_id:[0-9]+}/killmails[/]', ['GET'])]
    public function killmails(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(['character_id' => $character_id]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        $killmails = $this->killmails->aggregate([
            ['$match' => [
                '$or' => [
                    ['victim.character_id' => $character_id],
                    ['attackers.character_id' => $character_id],
                ],
            ]],
            ['$project' => ['_id' => 0, 'killmail_id' => 1]],
        ], [
            'allowDiskUse' => true,
            'maxTimeMS' => 60000
        ], 3600)->map(function ($killmail) {
            return $killmail['killmail_id'];
        });

        return $this->json($killmails, 3600);
    }

    #[RouteAttribute('/characters/{character_id:[0-9]+}/killmails/count[/]', ['GET'])]
    public function killmailsCount(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(['character_id' => $character_id]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        $killCount = $this->killmails->count(['attackers.character_id' => $character_id], [], 300);
        $lossCount = $this->killmails->count(['victim.character_id' => $character_id], [], 300);

        return $this->json(['kills' => $killCount, 'losses' => $lossCount], 300);
    }

    #[RouteAttribute('/characters/{character_id:[0-9]+}/killmails/latest[/]', ['GET'])]
    public function latestKillmails(int $character_id): ResponseInterface
    {
        $limit = (int) $this->getParam('limit', 1000);
        if ($limit > 1000 || $limit < 1) {
            return $this->json(['error' => 'Wrong limit', 'range' => '1-1000'], 300);
        }

        $character = $this->characters->findOne(['character_id' => $character_id]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        $killmails = $this->killmails->aggregate([
            ['$match' => [
                '$or' => [
                    ['victim.character_id' => $character_id],
                    ['attackers.character_id' => $character_id],
                ],
            ]],
            ['$sort' => ['kill_time' => -1]],
            ['$limit' => $limit],
            ['$project' => ['_id' => 0, 'killmail_id' => 1]],
        ], [], 3600)->map(function ($killmail) {
            return $killmail['killmail_id'];
        });

        return $this->json($killmails, 3600);
    }

    #[RouteAttribute('/characters/{character_id:[0-9]+}/top/ships[/]', ['GET'])]
    public function topShips(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(['character_id' => $character_id]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        $topShips = $this->topLists->topShips('character_id', $character_id);

        return $this->json($topShips, 300);
    }

    #[RouteAttribute('/characters/{character_id:[0-9]+}/top/systems[/]', ['GET'])]
    public function topSystems(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(['character_id' => $character_id]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        $topSystems = $this->topLists->topSystems('character_id', $character_id);

        return $this->json($topSystems, 300);
    }

    #[RouteAttribute('/characters/{character_id:[0-9]+}/top/regions[/]', ['GET'])]
    public function topRegions(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(['character_id' => $character_id]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        $topRegions = $this->topLists->topRegions('character_id', $character_id);

        return $this->json($topRegions, 300);
    }
}
