<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Http\Twig\Twig;
use Psr\Http\Message\ResponseInterface;

class Characters extends Controller
{
    public function __construct(
        protected \EK\Models\Characters $characters,
        protected \EK\Helpers\TopLists $topLists,
        protected Twig $twig
    ) {
        parent::__construct($twig);
    }

    #[RouteAttribute('/characters[/]', ['GET'])]
    public function all(): ResponseInterface
    {
        $characters = $this->characters->find([], ['projection' => ['character_id' => 1]], 300)->map(function ($character) {
            return $character['character_id'];
        });

        return $this->json($characters->toArray(), 300);
    }

    #[RouteAttribute('/characters/count[/]', ['GET'])]
    public function count(): ResponseInterface
    {
        return $this->json(['count' => $this->characters->count()], 300);
    }

    #[RouteAttribute('/characters/{character_id}[/]', ['GET'])]
    public function character(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(['character_id' => $character_id], ['projection' => ['_id' => 0]]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        return $this->json($this->cleanupTimestamps($character->toArray()), 300);
    }

    #[RouteAttribute('/characters/{character_id}/top/ships[/]', ['GET'])]
    public function topShips(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(['character_id' => $character_id]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        $topShips = $this->topLists->topShips('character_id', $character_id);

        return $this->json($topShips, 300);
    }

    #[RouteAttribute('/characters/{character_id}/top/systems[/]', ['GET'])]
    public function topSystems(int $character_id): ResponseInterface
    {
        $character = $this->characters->findOne(['character_id' => $character_id]);
        if ($character->isEmpty()) {
            return $this->json(['error' => 'Character not found'], 300);
        }

        $topSystems = $this->topLists->topSystems('character_id', $character_id);

        return $this->json($topSystems, 300);
    }

    #[RouteAttribute('/characters/{character_id}/top/regions[/]', ['GET'])]
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
