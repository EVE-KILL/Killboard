<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use Psr\Http\Message\ResponseInterface;

class KillList extends Controller
{
    public function __construct(protected \EK\Helpers\KillList $killlistHelper)
    {
        parent::__construct();
    }

    #[RouteAttribute("/killlist/latest[/{page:[0-9]+}]", ["GET"])]
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

    // Create api endpoints for the following:
    // latest|abyssal|wspace|highsec|lowsec|nullsec|big|solo|npc|5b|10b|citadels|t1|t2|t3|frigate|destroyers|cruisers|battlecruisers|battleships|capitals|freighters|supercarriers|titans
    // Each function should just call the $this->latest() function with the correct parameters

    #[RouteAttribute("/killlist/abyssal[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/wspace[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/highsec[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/lowsec[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/nullsec[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/big[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/solo[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/npc[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/5b[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/10b[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/citadels[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/t1[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/t2[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/t3[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/frigate[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/destroyers[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/cruisers[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/battlecruisers[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/battleships[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/capitals[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/freighters[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/supercarriers[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/titans[/{page:[0-9]+}]", ["GET"])]
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

    #[RouteAttribute("/killlist/{type}/{value:[0-9]+}[/{page:[0-9]+}]", ["GET"])]
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
