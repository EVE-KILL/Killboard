<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use Psr\Http\Message\ResponseInterface;

class Battles extends Controller
{
    public function __construct(protected \EK\Models\Battles $battles)
    {
        parent::__construct();
    }

    #[RouteAttribute("/battles[/]", ["GET"])]
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

    #[RouteAttribute("/battles/{id:[a-zA-Z0-9]+}[/]", ["GET"])]
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
}
