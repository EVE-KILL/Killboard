<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Http\Twig\Twig;
use Psr\Http\Message\ResponseInterface;

class Alliances extends Controller
{
    public function __construct(
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Characters $characters,
        protected \EK\Helpers\TopLists $topLists,
        protected Twig $twig
    ) {
        parent::__construct($twig);
    }

    #[RouteAttribute('/alliances[/]', ['GET'])]
    public function all(): ResponseInterface
    {
        $alliances = $this->alliances->find([], ['projection' => ['alliance_id' => 1]], 300)->map(function ($alliance) {
            return $alliance['alliance_id'];
        });

        return $this->json($alliances->toArray(), 300);
    }

    #[RouteAttribute('/alliances/count[/]', ['GET'])]
    public function count(): ResponseInterface
    {
        return $this->json(['count' => $this->alliances->count()], 300);
    }

    #[RouteAttribute('/alliances/{alliance_id}[/]', ['GET'])]
    public function alliance(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(['alliance_id' => $alliance_id], ['projection' => ['_id' => 0]]);
        if ($alliance->isEmpty()) {
            return $this->json(['error' => 'Alliance not found'], 300);
        }

        return $this->json($this->cleanupTimestamps($alliance->toArray()), 300);
    }

    #[RouteAttribute('/alliances/{alliance_id}/members[/]', ['GET'])]
    public function members(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(['alliance_id' => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(['error' => 'Alliance not found'], 300);
        }

        $members = $this->characters->find(['alliance_id' => $alliance_id], ['projection' => ['_id' => 0]], 300)->map(function ($member) {
            return $this->cleanupTimestamps($member);
        });

        return $this->json($members->toArray(), 300);
    }

    #[RouteAttribute('/alliances/{alliance_id}/members/characters[/]', ['GET'])]
    public function characters(int $alliance_id): ResponseInterface
    {
        return $this->members($alliance_id);
    }

    #[RouteAttribute('/alliances/{alliance_id}/members/corporations[/]', ['GET'])]
    public function corporations(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(['alliance_id' => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(['error' => 'Alliance not found'], 300);
        }

        $members = $this->corporations->find(['alliance_id' => $alliance_id], ['projection' => ['_id' => 0]], 300)->map(function ($member) {
            return $this->cleanupTimestamps($member);
        });

        return $this->json($members->toArray(), 300);
    }

    #[RouteAttribute('/alliances/{alliance_id}/top/characters[/]', ['GET'])]
    public function topCharacters(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(['alliance_id' => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(['error' => 'Alliance not found'], 300);
        }

        $topCharacters = $this->topLists->topCharacters('alliance_id', $alliance_id);

        return $this->json($topCharacters, 300);
    }

    #[RouteAttribute('/alliances/{alliance_id}/top/corporations[/]', ['GET'])]
    public function topCorporations(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(['alliance_id' => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(['error' => 'Alliance not found'], 300);
        }

        $topCorporations = $this->topLists->topCorporations('alliance_id', $alliance_id);

        return $this->json($topCorporations, 300);
    }

    #[RouteAttribute('/alliances/{alliance_id}/top/ships[/]', ['GET'])]
    public function topShips(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(['alliance_id' => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(['error' => 'Alliance not found'], 300);
        }

        $topShips = $this->topLists->topShips('alliance_id', $alliance_id);

        return $this->json($topShips, 300);
    }

    #[RouteAttribute('/alliances/{alliance_id}/top/systems[/]', ['GET'])]
    public function topSystems(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(['alliance_id' => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(['error' => 'Alliance not found'], 300);
        }

        $topSystems = $this->topLists->topSystems('alliance_id', $alliance_id);

        return $this->json($topSystems, 300);
    }

    #[RouteAttribute('/alliances/{alliance_id}/top/regions[/]', ['GET'])]
    public function topRegions(int $alliance_id): ResponseInterface
    {
        $alliance = $this->alliances->findOne(['alliance_id' => $alliance_id]);
        if ($alliance->isEmpty()) {
            return $this->json(['error' => 'Alliance not found'], 300);
        }

        $topRegions = $this->topLists->topRegions('alliance_id', $alliance_id);

        return $this->json($topRegions, 300);
    }
}
