<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Logger\StdOutLogger;
use EK\Models\Alliances;
use EK\Models\Characters;
use EK\Models\Corporations;
use EK\Models\StatsHistorical;

class UpdateMemberCount extends Cronjob
{
    protected string $cronTime = '0 4 * * *';

    public function __construct(
        protected Alliances $alliances,
        protected Corporations $corporations,
        protected Characters $characters,
        protected StatsHistorical $statsHistorical,
        protected StdOutLogger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        $currentDate = (new \DateTime())->format('Y-m-d');
        $this->updateAlliances($currentDate);
        $this->updateCorporations($currentDate);
    }

    private function updateAlliances(string $currentDate): void
    {
        $aggregationPipeline = [
            [
                '$match' => [
                    'alliance_id' => ['$ne' => 0]
                ]
            ],
            [
                '$group' => [
                    '_id' => '$alliance_id',
                    'member_count' => ['$sum' => 1]
                ]
            ],
            [
                '$project' => [
                    '_id' => 0,
                    'alliance_id' => '$_id',
                    'member_count' => 1
                ]
            ]
        ];

        $allianceMemberCounts = $this->characters->aggregate($aggregationPipeline, ['hint' => 'alliance_id']);

        $allianceUpdates = [];
        $historicalAllianceUpdates = [];
        foreach ($allianceMemberCounts as $alliance) {
            $allianceUpdates[] = ['alliance_id' => $alliance['alliance_id'], 'member_count' => $alliance['member_count']];
            $historicalAllianceUpdates[] = [
                'id' => $alliance['alliance_id'],
                'type' => 'alliance',
                'history' => [
                    $currentDate => $alliance['member_count']
                ]
            ];

            if (count($allianceUpdates) >= 1000) {
                $this->saveAlliancesAndHistoricalData($allianceUpdates, $historicalAllianceUpdates, $currentDate);
                $allianceUpdates = [];
                $historicalAllianceUpdates = [];
            }
        }

        if (!empty($allianceUpdates)) {
            $this->saveAlliancesAndHistoricalData($allianceUpdates, $historicalAllianceUpdates, $currentDate);
        }
    }

    private function updateCorporations(string $currentDate): void
    {
        $aggregationPipeline = [
            [
                '$match' => [
                    'corporation_id' => ['$ne' => 0]
                ]
            ],
            [
                '$group' => [
                    '_id' => '$corporation_id',
                    'member_count' => ['$sum' => 1]
                ]
            ],
            [
                '$project' => [
                    '_id' => 0,
                    'corporation_id' => '$_id',
                    'member_count' => 1
                ]
            ]
        ];

        $corporationMemberCounts = $this->characters->aggregate($aggregationPipeline, ['hint' => 'corporation_id']);

        $corporationUpdates = [];
        $historicalCorporationUpdates = [];
        foreach ($corporationMemberCounts as $corporation) {
            $corporationUpdates[] = ['corporation_id' => $corporation['corporation_id'], 'member_count' => $corporation['member_count']];
            $historicalCorporationUpdates[] = [
                'id' => $corporation['corporation_id'],
                'type' => 'corporation',
                'history' => [
                    $currentDate => $corporation['member_count']
                ]
            ];

            if (count($corporationUpdates) >= 10000) {
                $this->saveCorporationsAndHistoricalData($corporationUpdates, $historicalCorporationUpdates, $currentDate);
                $corporationUpdates = [];
                $historicalCorporationUpdates = [];
            }
        }

        if (!empty($corporationUpdates)) {
            $this->saveCorporationsAndHistoricalData($corporationUpdates, $historicalCorporationUpdates, $currentDate);
        }
    }

    private function saveAlliancesAndHistoricalData(array $allianceUpdates, array $historicalUpdates, string $currentDate): void
    {
        $this->alliances->setData($allianceUpdates);
        $this->alliances->saveMany();

        $bulkWrites = [];
        foreach ($historicalUpdates as $update) {
            $bulkWrites[] = [
                'updateOne' => [
                    [
                        'id' => $update['id'],
                        'type' => $update['type']
                    ],
                    [
                        '$set' => [
                            'type' => $update['type'],
                            'history.' . $currentDate => $update['history'][$currentDate]
                        ]
                    ],
                    [
                        'upsert' => true
                    ]
                ]
            ];
        }

        $this->statsHistorical->collection->bulkWrite($bulkWrites);
    }

    private function saveCorporationsAndHistoricalData(array $corporationUpdates, array $historicalUpdates, string $currentDate): void
    {
        $this->corporations->setData($corporationUpdates);
        $this->corporations->saveMany();

        $bulkWrites = [];
        foreach ($historicalUpdates as $update) {
            $bulkWrites[] = [
                'updateOne' => [
                    [
                        'id' => $update['id'],
                        'type' => $update['type']
                    ],
                    [
                        '$set' => [
                            'type' => $update['type'],
                            'history.' . $currentDate => $update['history'][$currentDate]
                        ]
                    ],
                    [
                        'upsert' => true
                    ]
                ]
            ];
        }

        $this->statsHistorical->collection->bulkWrite($bulkWrites);
    }
}
