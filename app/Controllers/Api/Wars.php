<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Http\Twig\Twig;
use Psr\Http\Message\ResponseInterface;

class Wars extends Controller
{
    public function __construct(
        protected \EK\Models\Wars $wars,
        protected \EK\Models\Killmails $killmails,
    ) {
        parent::__construct();
    }

    #[RouteAttribute('/wars[/]', ['GET'])]
    public function wars(): ResponseInterface
    {
        // Fetch all wars, and project only id
        $wars = $this->wars->find([], ['projection' => ['id' => 1]])->map(fn($war) => $war['id']);
        return $this->json($wars);
    }

    #[RouteAttribute('/wars/{warId}[/]', ['GET'])]
    public function war(int $warId): ResponseInterface
    {
        if ($warId === 0) {
            return $this->json([]);
        }

        $war = $this->wars->findOne(['id' => $warId], ['projection' => ['_id' => 0]]);
        // Fix all the timestamps into a readable format
        $timestampFields = ['declared', 'finished', 'last_modified', 'retracted', 'started'];
        foreach($timestampFields as $field) {
            if (isset($war[$field])) {
                $war[$field] = $war[$field]->toDateTime()->format('Y-m-d H:i:s');
            }
        }
        return $this->json($war);
    }
    #[RouteAttribute('/wars/{warId}/killmails[/]', ['GET'])]
    public function killmails(int $warId): ResponseInterface
    {
        if ($warId === 0) {
            return $this->json([]);
        }

        $kills = $this->killmails->find(['war_id' => $warId], ['projection' => ['_id' => 0, 'kill_time_str' => 0]]);
        // Fix all the timestamps into a readable format
        $timestampFields = ['last_modified', 'kill_time'];
        foreach($timestampFields as $field) {
            if (isset($kills[$field])) {
                $kills[$field] = $kills[$field]->toDateTime()->format('Y-m-d H:i:s');
            }
        }

        return $this->json($kills);
    }

}
