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
        if ($data->has('error')) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute('/killlist/{type}/{value:[0-9]+}[/{page:[0-9]+}]', ['GET'])]
    public function killsForType(string $type, int $value, int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getKillsForType($type, $value, $page, 100);
        if ($data->has('error')) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });
        return $this->json($data, 60);
    }
}
