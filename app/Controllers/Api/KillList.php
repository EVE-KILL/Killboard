<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Helpers\TopLists;
use EK\Http\Twig\Twig;
use EK\Models\Killmails;
use Psr\Http\Message\ResponseInterface;

class KillList extends Controller
{
    public function __construct(
        protected \EK\Helpers\KillList $killlistHelper,
    ) {
        parent::__construct();
    }

    #[RouteAttribute('/killlist/latest[/{page:[0-9]+}]', ['GET'])]
    public function latest(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getLatest($page, 100);
        return $this->json($data, 60);
    }
}
