<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Models\Killmails;
use EK\Models\Wars as ModelsWars;
use Psr\Http\Message\ResponseInterface;

class Wars extends Controller
{
    public function __construct(
        protected ModelsWars $wars,
        protected Killmails $killmails
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/wars[/]", ["GET"], "Get all wars")]
    public function wars(): ResponseInterface
    {
        // Fetch all wars and collect only the IDs
        $warsGenerator = $this->wars->find([], ["projection" => ["id" => 1]]);
        $wars = [];

        foreach ($warsGenerator as $war) {
            $wars[] = $war["id"];
        }

        return $this->json($wars);
    }

    #[RouteAttribute("/wars/{war_id}[/]", ["GET"], "Get a war by ID")]
    public function war(int $war_id): ResponseInterface
    {
        if ($war_id === 0) {
            return $this->json([]);
        }

        $war = $this->wars->findOne(
            ["id" => $war_id],
            ["projection" => ["_id" => 0]]
        );

        // Fix all the timestamps into a readable format
        $timestampFields = [
            "declared",
            "finished",
            "last_modified",
            "retracted",
            "started",
        ];
        foreach ($timestampFields as $field) {
            if (isset($war[$field])) {
                $war[$field] = $war[$field]
                    ->toDateTime()
                    ->format("Y-m-d H:i:s");
            }
        }
        return $this->json($war);
    }

    #[RouteAttribute("/wars/{war_id}/killmails[/]", ["GET"], "Get all killmails for a war")]
    public function killmails(int $war_id): ResponseInterface
    {
        if ($war_id === 0) {
            return $this->json([]);
        }

        $killsGenerator = $this->killmails->find(
            ["war_id" => $war_id],
            ["projection" => ["_id" => 0, "kill_time_str" => 0]]
        );

        $kills = [];

        foreach ($killsGenerator as $kill) {
            // Fix all the timestamps into a readable format
            $timestampFields = ["last_modified", "kill_time"];
            foreach ($timestampFields as $field) {
                if (isset($kill[$field])) {
                    $kill[$field] = $kill[$field]
                        ->toDateTime()
                        ->format("Y-m-d H:i:s");
                }
            }

            $kills[] = $kill;
        }

        return $this->json($kills);
    }
}
