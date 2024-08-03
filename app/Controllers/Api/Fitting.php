<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Helpers\Killmails as HelpersKillmails;
use EK\Models\Killmails;
use EK\Models\Prices;
use EK\Models\TypeIDs;
use Psr\Http\Message\ResponseInterface;

class Fitting extends Controller
{
    protected array $shipGroupIds = [
        25, 26, 27, 28, 29, 30, 31,
        237, 324, 358, 380, 381, 419,
        420, 463, 485, 513, 540, 541,
        543, 547, 659, 830, 831, 832,
        833, 834, 883, 893, 894, 898,
        900, 902, 906, 941, 963, 1022,
        1201, 1202, 1283, 1305, 1527,
        1534, 1538, 1972, 2001, 4594
    ];

    public function __construct(
        protected Killmails $killmails,
        protected HelpersKillmails $killmailsHelper,
        protected TypeIDs $typeIDs,
        protected Prices $prices
    ) {
    }

    #[RouteAttribute("/fitting/top10/{ship_id:[0-9]+}[/]", ["GET"])]
    public function top10ShipFits(int $ship_id): ResponseInterface
    {
        $item = $this->typeIDs->findOneOrNull(['type_id' => $ship_id]);
        if ($item === null || !in_array($item['group_id'], $this->shipGroupIds)) {
            return $this->json(['error' => 'Invalid ship ID']);
        }

        $desiredCount = 10;
        $limit = $desiredCount * 2; // Set initial limit higher to account for potential skips
        $validResults = [];

        while (count($validResults) < $desiredCount) {
            $pipeline = [
                [
                    '$match' => [
                        'kill_time' => [
                            '$gte' => new \MongoDB\BSON\UTCDateTime((new \DateTime())->modify('-30 days')->getTimestamp() * 1000),
                            '$lte' => new \MongoDB\BSON\UTCDateTime()
                        ],
                        'victim.ship_id' => $ship_id
                    ]
                ],
                [
                    '$group' => [
                        '_id' => '$dna',
                        'count' => ['$sum' => 1],
                        'killmail_ids' => ['$push' => '$killmail_id'],
                        'items' => ['$first' => '$items'], // assuming items array is same for each dna
                        'ship_image_url' => ['$first' => '$victim.ship_image_url'],
                        'fitting_value' => ['$first' => '$fitting_value']
                    ]
                ],
                [
                    '$sort' => ['count' => -1]
                ],
                [
                    '$limit' => $limit
                ],
                [
                    '$project' => [
                        'dna' => '$_id',
                        'count' => 1,
                        'killmail_ids' => 1,
                        'items' => 1,
                        'ship_image_url' => 1,
                        'fitting_value' => 1
                    ]
                ]
            ];

            $result = $this->killmails->aggregate($pipeline);

            foreach ($result as $res) {
                if (empty($res['dna']) || empty($res['items'])) {
                    continue;
                }

                $decodedItems = $this->killmailsHelper->decodeDNA($res['items']);
                if (!empty($decodedItems)) {
                    $shipValue = $this->prices->getPriceByTypeId($ship_id, new \MongoDB\BSON\UTCDateTime((new \DateTime())->getTimestamp() * 1000));
                    $rank = count($validResults) + 1;
                    $svg = $this->generateSVG($decodedItems, $res['ship_image_url'], $res['fitting_value'], $shipValue, $rank);
                    $validResults[] = [
                        'dna' => $res['dna'],
                        'count' => $res['count'],
                        'killmail_ids' => $res['killmail_ids'],
                        'fitting' => $decodedItems,
                        'svg' => $svg
                    ];
                }

                if (count($validResults) >= $desiredCount) {
                    break;
                }
            }

            if (count($validResults) < $desiredCount) {
                $limit += $desiredCount; // Increase the limit to fetch more potential results
            }
        }

        return $this->json(array_slice($validResults, 0, $desiredCount));
    }

    private function generateSVG(array $fitting, string $shipImageUrl, float $fitCost, float $shipCost, int $rank): string
    {
        $totalCost = $fitCost + $shipCost;

        $svg = '<svg width="300" height="60" xmlns="http://www.w3.org/2000/svg">';

        // Ship image
        $svg .= '<image href="' . $shipImageUrl . '" x="0" y="0" height="60" width="60"/>';

        // Rank
        $svg .= '<text x="70" y="15" font-family="Arial" font-size="12" fill="white">Rank: ' . $rank . '</text>';

        // Fit cost
        $svg .= '<text x="70" y="30" font-family="Arial" font-size="12" fill="white">Fit Cost: ' . number_format($fitCost, 2) . ' ISK</text>';

        // Ship cost
        $svg .= '<text x="70" y="45" font-family="Arial" font-size="12" fill="white">Ship Cost: ' . number_format($shipCost, 2) . ' ISK</text>';

        // Total cost
        $svg .= '<text x="70" y="60" font-family="Arial" font-size="12" fill="white">Total Cost: ' . number_format($totalCost, 2) . ' ISK</text>';

        $svg .= '</svg>';

        return $svg;
    }
}
