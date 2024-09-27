<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Models\Celestials;
use EK\Models\Killmails;
use Psr\Http\Message\ResponseInterface;

class Intel extends Controller
{
    public function __construct(
        protected Killmails $killmails,
        protected Celestials $celestials
    ) {
        parent::__construct();

    }

    #[RouteAttribute("/intel/metenox", ["GET"], "Get Metenox moon locations based on killmails")]
    public function metenoxMoons(): ResponseInterface
    {
        $metenoxId = 81826;
        $killmails = $this->killmails->find(['victim.ship_id' => $metenoxId]);
        $killmails = $this->cleanupTimestamps($killmails);
        $return = [];

        // Take the x, y and z coordinates from the killmails and find the closest moon
        foreach ($killmails as $killmail) {
            // Limit the distance to 1000 AU in meters
            $distance = 1000 * 3.086e16;

            $celestials = $this->celestials->aggregate([
                ['$match' => [
                    'solar_system_id' => $killmail['system_id'],
                    'x' => ['$gt' => $killmail['x'] - $distance, '$lt' => $killmail['x'] + $distance],
                    'y' => ['$gt' => $killmail['y'] - $distance, '$lt' => $killmail['y'] + $distance],
                    'z' => ['$gt' => $killmail['z'] - $distance, '$lt' => $killmail['z'] + $distance],
                ]],
                ['$project' => [
                    'item_id' => 1,
                    'item_name' => 1,
                    'constellation_id' => 1,
                    'solar_system_id' => 1,
                    'solar_system_name' => 1,
                    'region_id' => 1,
                    'region_name' => 1,
                    'distance' => [
                        '$sqrt' => [
                            '$add' => [
                                ['$pow' => [['$subtract' => ['$x', $killmail['x']]], 2]],
                                ['$pow' => [['$subtract' => ['$y', $killmail['y']]], 2]],
                                ['$pow' => [['$subtract' => ['$z', $killmail['z']]], 2]],
                            ]
                        ]
                    ]
                ]],
                ['$sort' => ['distance' => 1]],
                ['$limit' => 1],
            ]);

            $celestial = iterator_to_array($celestials)[0];
            $return[] = [
                'system_id' => (int) $celestial['solar_system_id'],
                'system_name' => $celestial['solar_system_name'],
                'region_id' => (int) $celestial['region_id'],
                'region_name' => $celestial['region_name'],
                'moon_name' => $celestial['item_name'],
                'moon_id' => (int) $celestial['item_id'],
                'killmail_id' => (int) $killmail['killmail_id'],
                'moonType' => $this->classifyGoos($killmail),
                'items' => $killmail['items'] ?? [],
            ];
        }

        return $this->json($return);
    }

    private function classifyGoos(array $killmail): string
    {
        $gooTypes = [
            'R4' => [
                'Hydrocarbons',
                'Silicates',
                'Evaporite Deposits',
                'Atmospheric Gases',
            ],
            'R8' => [
                'Cobalt',
                'Scandium',
                'Tungsten',
                'Titanium',
            ],
            'R16' => [
                'Chromium',
                'Cadmium',
                'Platinum',
                'Vanadium',
            ],
            'R32' => [
                'Technetium',
                'Mercury',
                'Caesium',
                'Hafnium',
            ],
            'R64' => [
                'Promethium',
                'Neodymium',
                'Dysprosium',
                'Thulium',
            ],
        ];

        // Loop through the gooTypes and check if any of the names are in the $killmail['items'] array - if there are, return the R4, R8, R16, R32 or R64 identifier (Or unknown if there are no matches)
        foreach($killmail['items'] as $item) {
            foreach ($gooTypes as $type => $names) {
                if (in_array($item['type_name'], $names)) {
                    return $type;
                }
            }
        }

        return 'Unknown';
    }
}
