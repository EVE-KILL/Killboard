<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Helpers\KillList as KillListHelper;
use Psr\Http\Message\ResponseInterface;

class KillList extends Controller
{
    public function __construct(
        protected KillListHelper $killlistHelper
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/killlist/latest[/{page:[0-9]+}]", ["GET"], "Get the latest kills")]
    public function latest(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getLatest($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/abyssal[/{page:[0-9]+}]", ["GET"], "Get the latest abyssal kills")]
    public function abyssal(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getAbyssal($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/wspace[/{page:[0-9]+}]", ["GET"], "Get the latest wspace kills")]
    public function wspace(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getWspace($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/highsec[/{page:[0-9]+}]", ["GET"], "Get the latest highsec kills")]
    public function highsec(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getHighsec($page, 100, 300);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/lowsec[/{page:[0-9]+}]", ["GET"], "Get the latest lowsec kills")]
    public function lowsec(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getLowsec($page, 100, 300);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/nullsec[/{page:[0-9]+}]", ["GET"], "Get the latest nullsec kills")]
    public function nullsec(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getNullsec($page, 100, 300);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/big[/{page:[0-9]+}]", ["GET"], "Get the latest big kills")]
    public function big(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getBigKills($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/solo[/{page:[0-9]+}]", ["GET"], "Get the latest solo kills")]
    public function solo(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getSolo($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/npc[/{page:[0-9]+}]", ["GET"], "Get the latest NPC kills")]
    public function npc(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getNpc($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/5b[/{page:[0-9]+}]", ["GET"], "Get the latest 5b kills")]
    public function fiveB(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->get5b($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/10b[/{page:[0-9]+}]", ["GET"], "Get the latest 10b kills")]
    public function tenB(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->get10b($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/citadels[/{page:[0-9]+}]", ["GET"], "Get the latest citadel kills")]
    public function citadels(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getCitadels($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/t1[/{page:[0-9]+}]", ["GET"], "Get the latest T1 kills")]
    public function t1(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getT1($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/t2[/{page:[0-9]+}]", ["GET"], "Get the latest T2 kills")]
    public function t2(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getT2($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/t3[/{page:[0-9]+}]", ["GET"], "Get the latest T3 kills")]
    public function t3(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getT3($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/frigate[/{page:[0-9]+}]", ["GET"], "Get the latest frigate kills")]
    public function frigate(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getFrigates($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/destroyers[/{page:[0-9]+}]", ["GET"], "Get the latest destroyer kills")]
    public function destroyers(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getDestroyers($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/cruisers[/{page:[0-9]+}]", ["GET"], "Get the latest cruiser kills")]
    public function cruisers(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getCruisers($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/battlecruisers[/{page:[0-9]+}]", ["GET"], "Get the latest battlecruiser kills")]
    public function battlecruisers(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getBattlecruisers($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/battleships[/{page:[0-9]+}]", ["GET"], "Get the latest battleship kills")]
    public function battleships(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getBattleships($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/capitals[/{page:[0-9]+}]", ["GET"], "Get the latest capital kills")]
    public function capitals(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getCapitals($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/freighters[/{page:[0-9]+}]", ["GET"], "Get the latest freighter kills")]
    public function freighters(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getFreighters($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/supercarriers[/{page:[0-9]+}]", ["GET"], "Get the latest supercarrier kills")]
    public function supercarriers(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getSupercarriers($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/titans[/{page:[0-9]+}]", ["GET"], "Get the latest titan kills")]
    public function titans(int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getTitans($page, 100);
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });

        return $this->json($data, 60);
    }

    #[RouteAttribute("/killlist/{type}/{value:[0-9]+}[/{page:[0-9]+}]", ["GET"], "Get kills for a specific type")]
    public function killsForType(string $type, int $value, int $page = 1): ResponseInterface
    {
        $data = $this->killlistHelper->getKillsForType(
            $type,
            $value,
            $page,
            100
        );
        if ($data->has("error")) {
            return $this->json($data, 300);
        }

        $data = $data->map(function ($kill) {
            return $this->cleanupTimestamps($kill);
        });
        return $this->json($data, 60);
    }

}
