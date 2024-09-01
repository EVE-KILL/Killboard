<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Models\Celestials as ModelsCelestials;
use Psr\Http\Message\ResponseInterface;

class Celestials extends Controller
{
    public function __construct(
        protected ModelsCelestials $celestials
    ) {
    }

    #[RouteAttribute('/celestials/count[/]', ['GET'], 'Get the count of all celestials')]
    public function count(): ResponseInterface
    {
        return $this->json([
            'count' => $this->celestials->aproximateCount(),
        ]);
    }

    #[RouteAttribute('/celestials/system/{system_id:[0-9]+}[/]', ['GET'], 'Get all celestials in a system')]
    public function system(int $system_id): ResponseInterface
    {
        $celestials = $this->celestials->find(
            ['solar_system_id' => $system_id],
            ['projection' => ['_id' => 0]]
        )->toArray();

        $celestials = $this->cleanupTimestamps($celestials);
        return $this->json($celestials);
    }

    #[RouteAttribute('/celestials/region/{region_id:[0-9]+}[/]', ['GET'], 'Get all celestials in a region')]
    public function region(int $region_id): ResponseInterface
    {
        $celestials = $this->celestials->find(
            ['region_id' => $region_id],
            ['projection' => ['_id' => 0]]
        )->toArray();

        $celestials = $this->cleanupTimestamps($celestials);
        return $this->json($celestials);
    }

    #[RouteAttribute('/celestials/{celestial_id:[0-9]+}[/]', ['GET'], 'Get a celestial by ID')]
    public function celestial(int $celestial_id): ResponseInterface
    {
        $celestial = $this->celestials->findOneOrNull(
            ['item_id' => $celestial_id],
            ['projection' => ['_id' => 0]]
        )->toArray();

        if ($celestial === null) {
            return $this->json(
                [
                    'error' => 'Celestial not found',
                ],
                300
            );
        }

        $celestial = $this->cleanupTimestamps($celestial);
        return $this->json($celestial);
    }
}
