<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Models\Killmails;
use Psr\Http\Message\ResponseInterface;

class Intel extends Controller
{
    public function __construct(
        protected Killmails $killmails
    ) {
        parent::__construct();

    }

    #[RouteAttribute("/intel/metenox", ["GET"], "Get Metenox moon locations based on killmails")]
    public function metenoxMoons(): ResponseInterface
    {
        $metenoxId = 81826;
        $killmails = $this->killmails->find(['victim.ship_id' => $metenoxId]);

        $killmails = $this->cleanupTimestamps($killmails);

        return $this->json($killmails);
    }

}
