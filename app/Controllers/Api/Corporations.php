<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Http\Twig\Twig;
use Psr\Http\Message\ResponseInterface;

class Corporations extends Controller
{
    public function __construct(
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Characters $characters,
        protected \EK\Helpers\TopLists $topLists,
        protected \EK\Models\Killmails $killmails,
    ) {
        parent::__construct();
    }

    #[RouteAttribute('/corporations[/]', ['GET'])]
    public function all(): ResponseInterface
    {
        $corporations = $this->corporations->find([], ['projection' => ['corporation_id' => 1]], 300)->map(function ($corporation) {
            return $corporation['corporation_id'];
        });

        return $this->json($corporations->toArray(), 300);
    }

    #[RouteAttribute('/corporations/count[/]', ['GET'])]
    public function count(): ResponseInterface
    {
        return $this->json(['count' => $this->corporations->count()], 300);
    }

    #[RouteAttribute('/corporations/{corporation_id}[/]', ['GET'])]
    public function corporation(int $corporation_id): ResponseInterface
    {
        $corporation = $this->corporations->findOne(['corporation_id' => $corporation_id], ['projection' => ['_id' => 0]]);
        if ($corporation->isEmpty()) {
            return $this->json(['error' => 'Corporation not found'], 300);
        }

        return $this->json($this->cleanupTimestamps($corporation->toArray()), 300);
    }

    #[RouteAttribute('/corporations[/]', ['POST'])]
    public function corporations(): ResponseInterface
    {
        $postData = json_validate($this->getBody()) ? json_decode($this->getBody(), true) : [];
        if (empty($postData)) {
            return $this->json(['error' => 'No data provided'], 300);
        }

        // Error if there are more than 1000 IDs
        if (count($postData) > 1000) {
            return $this->json(['error' => 'Too many IDs provided'], 300);
        }

        $corporations = $this->corporations->find(['corporation_id' => ['$in' => $postData]], ['projection' => ['_id' => 0], 'limit' => 1000])->map(function ($corporation) {
            return $this->cleanupTimestamps($corporation);
        });

        return $this->json($corporations->toArray(), 300);
    }

    #[RouteAttribute('/corporations/{corporation_id}/killmails[/]', ['GET'])]
    public function killmails(int $corporation_id): ResponseInterface
    {
        $corporation = $this->corporations->findOne(['corporation_id' => $corporation_id]);
        if ($corporation->isEmpty()) {
            return $this->json(['error' => 'Corporation not found'], 300);
        }

        $killmails = $this->killmails->aggregate([
            ['$match' => [
                '$or' => [
                    ['victim.corporation_id' => $corporation_id],
                    ['attackers.corporation_id' => $corporation_id],
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

    #[RouteAttribute('/corporations/{corporation_id}/killmails/count[/]', ['GET'])]
    public function killmailsCount(int $corporation_id): ResponseInterface
    {
        $corporation = $this->corporations->findOne(['corporation_id' => $corporation_id]);
        if ($corporation->isEmpty()) {
            return $this->json(['error' => 'Corporation not found'], 300);
        }

        $killCount = $this->killmails->count(['attackers.corporation_id' => $corporation_id], []);
        $lossCount = $this->killmails->count(['victim.corporation_id' => $corporation_id], []);

        return $this->json(['kills' => $killCount, 'losses' => $lossCount], 300);
    }

    #[RouteAttribute('/corporations/{corporation_id}/killmails/latest[/]', ['GET'])]
    public function latestKillmails(int $corporation_id): ResponseInterface
    {
        $limit = (int) $this->getParam('limit', 1000);
        if ($limit > 1000 || $limit < 1) {
            return $this->json(['error' => 'Wrong limit', 'range' => '1-1000'], 300);
        }

        $corporation = $this->corporations->findOne(['corporation_id' => $corporation_id]);
        if ($corporation->isEmpty()) {
            return $this->json(['error' => 'Corporation not found'], 300);
        }

        $killmails = $this->killmails->aggregate([
            ['$match' => [
                '$or' => [
                    ['victim.corporation_id' => $corporation_id],
                    ['attackers.corporation_id' => $corporation_id],
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

    #[RouteAttribute('/corporations/{corporation_id}/members[/]', ['GET'])]
    public function members(int $corporation_id): ResponseInterface
    {
        $corporation = $this->corporations->findOne(['corporation_id' => $corporation_id]);
        if ($corporation->isEmpty()) {
            return $this->json(['error' => 'Corporation not found'], 300);
        }

        $members = $this->characters->find(['corporation_id' => $corporation_id], ['projection' => ['_id' => 0]], 300)->map(function ($member) {
            return $this->cleanupTimestamps($member);
        });

        return $this->json($members->toArray(), 300);
    }

    #[RouteAttribute('/corporations/{corporation_id}/top/characters[/]', ['GET'])]
    public function topCharacters(int $corporation_id): ResponseInterface
    {
        $corporation = $this->corporations->findOne(['corporation_id' => $corporation_id]);
        if ($corporation->isEmpty()) {
            return $this->json(['error' => 'Corporation not found'], 300);
        }

        $topCharacters = $this->topLists->topCharacters('corporation_id', $corporation_id);

        return $this->json($topCharacters, 300);
    }

    #[RouteAttribute('/corporations/{corporation_id}/top/ships[/]', ['GET'])]
    public function topShips(int $corporation_id): ResponseInterface
    {
        $corporation = $this->corporations->findOne(['corporation_id' => $corporation_id]);
        if ($corporation->isEmpty()) {
            return $this->json(['error' => 'Corporation not found'], 300);
        }

        $topShips = $this->topLists->topShips('corporation_id', $corporation_id);

        return $this->json($topShips, 300);
    }

    #[RouteAttribute('/corporations/{corporation_id}/top/systems[/]', ['GET'])]
    public function topSystems(int $corporation_id): ResponseInterface
    {
        $corporation = $this->corporations->findOne(['corporation_id' => $corporation_id]);
        if ($corporation->isEmpty()) {
            return $this->json(['error' => 'Corporation not found'], 300);
        }

        $topSystems = $this->topLists->topSystems('corporation_id', $corporation_id);

        return $this->json($topSystems, 300);
    }

    #[RouteAttribute('/corporations/{corporation_id}/top/regions[/]', ['GET'])]
    public function topRegions(int $corporation_id): ResponseInterface
    {
        $corporation = $this->corporations->findOne(['corporation_id' => $corporation_id]);
        if ($corporation->isEmpty()) {
            return $this->json(['error' => 'Corporation not found'], 300);
        }

        $topRegions = $this->topLists->topRegions('corporation_id', $corporation_id);

        return $this->json($topRegions, 300);
    }
}
