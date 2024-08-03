<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Helpers\Killmails as HelpersKillmails;
use EK\Models\Killmails;
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
        protected TypeIDs $typeIDs
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
                        'items' => ['$first' => '$items'] // assuming items array is same for each dna
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
                        'items' => 1
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
                    $validResults[] = [
                        'dna' => $res['dna'],
                        'count' => $res['count'],
                        'killmail_ids' => $res['killmail_ids'],
                        'fitting' => $decodedItems
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
}
