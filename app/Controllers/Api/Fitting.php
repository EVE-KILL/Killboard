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
                        'killmails' => [
                            '$push' => [
                                'killmail_id' => '$killmail_id',
                                'hash' => '$hash'
                            ]
                        ],
                        'ship_image_url' => ['$first' => '$victim.ship_image_url']
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
                        'killmails' => 1,
                        'ship_image_url' => 1
                    ]
                ]
            ];

            $result = $this->killmails->aggregate($pipeline);

            foreach ($result as $res) {
                if (empty($res['killmails'])) {
                    continue;
                }

                $firstKillmail = $res['killmails'][0];
                $killmailDetails = $this->killmails->findOne(['killmail_id' => $firstKillmail['killmail_id']]);

                if ($killmailDetails) {
                    $fitting = $this->generateFitting($killmailDetails['items']);
                    if (!empty(array_filter($fitting))) {
                        $shipValue = $this->prices->getPriceByTypeId($ship_id, new \MongoDB\BSON\UTCDateTime((new \DateTime())->getTimestamp() * 1000));
                        $fitCost = $this->calculateFitCost($fitting);
                        $rank = count($validResults) + 1;
                        $svg = $this->generateSVG($fitting, $res['ship_image_url'], $fitCost, $shipValue, $rank);

                        $killmailLinks = [];
                        foreach ($res['killmails'] as $killmail) {
                            $killmailLinks[] = [
                                'killmail_id' => $killmail['killmail_id'],
                                'hash' => $killmail['hash']
                            ];
                        }

                        $validResults[] = [
                            'dna' => $res['dna'],
                            'count' => $res['count'],
                            'killmails' => $killmailLinks,
                            'fitting' => $fitting,
                            'svg' => $svg
                        ];
                    }
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

    private function generateFitting(array $items): array
    {
        $itemSlotTypes = $this->itemSlotTypes();
        $fittingArray = [
            'high_slot' => [],
            'medium_slot' => [],
            'low_slot' => [],
            'rig_slot' => [],
            'subsystem' => [],
            'drone_bay' => [],
            'fighter_bay' => []
        ];

        foreach ($items as $item) {
            $flag = $item['flag'];
            $typeID = $item['type_id'] ?? 0;
            $typeName = $item['type_name'] ?? '';
            $quantity = ($item['qty_dropped'] ?? 0) + ($item['qty_destroyed'] ?? 0);
            $value = $item['value'] ?? 0;

            foreach ($itemSlotTypes as $slotType => $slotFlags) {
                if (in_array($flag, $slotFlags)) {
                    $fittingArray[$slotType][] = [
                        'item_id' => $typeID,
                        'item_name' => $typeName,
                        'quantity' => $quantity,
                        'value' => $value
                    ];
                    break;
                }
            }
        }

        return $fittingArray;
    }

    private function itemSlotTypes(): array
    {
        return [
            'high_slot' => [27, 28, 29, 30, 31, 32, 33, 34],
            'medium_slot' => [19, 20, 21, 22, 23, 24, 25, 26],
            'low_slot' => [11, 12, 13, 14, 15, 16, 17, 18],
            'rig_slot' => [92, 93, 94, 95, 96, 97, 98, 99],
            'subsystem' => [125, 126, 127, 128, 129, 130, 131, 132],
            'drone_bay' => [87],
            'fighter_bay' => [158]
        ];
    }

    private function calculateFitCost(array $decodedItems): float
    {
        $totalCost = 0.0;

        foreach ($decodedItems as $slotItems) {
            foreach ($slotItems as $item) {
                $price = $this->prices->getPriceByTypeId($item['item_id'], new \MongoDB\BSON\UTCDateTime((new \DateTime())->getTimestamp() * 1000));
                $totalCost += $price * $item['quantity'];
            }
        }

        return $totalCost;
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
