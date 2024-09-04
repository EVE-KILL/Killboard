<?php

namespace EK\Helpers;

use EK\Models\Campaigns as CampaignModel;
use EK\Models\Killmails;

class Campaigns
{
    public function __construct(
        protected Killmails $killmails,
        protected CampaignModel $campaigns
    ) {
    }

    public function getAllCampaigns(): array
    {
        $campaigns = $this->campaigns->find([], [
            'projection' => [
                '_id' => 0,
                'last_modified' => 0,
                'user' => 0
            ]
        ], 0);

        foreach ($campaigns as $key => $campaign) {
            $campaigns[$key] = $this->generateCampaignStats($campaign);
        }

        return $campaigns->toArray();
    }

    protected function generateCampaignStats(array $campaign): array
    {
        $entities = $campaign['entities'] ?? [];
        $locations = $campaign['locations'] ?? [];
        $timePeriod = $campaign['timePeriods'][0] ?? null;

        // Build the initial match stages
        $pipeline = [];

        // Step 1: Match by time period
        if (!empty($timePeriod)) {
            $from = new \MongoDB\BSON\UTCDateTime(strtotime($timePeriod['from']) * 1000);
            $to = new \MongoDB\BSON\UTCDateTime(strtotime($timePeriod['to']) * 1000);
            $pipeline[] = ['$match' => ['kill_time' => ['$gte' => $from, '$lte' => $to]]];
        }

        // Step 2: Match by location first (this will use indexes on system_id/region_id)
        if (!empty($locations)) {
            $locationConditions = [];
            foreach ($locations as $location) {
                $id = $location['id'];
                $type = $location['type'];

                switch ($type) {
                    case 'system':
                        $locationConditions[] = ['system_id' => $id];
                        break;
                    case 'region':
                        $locationConditions[] = ['region_id' => $id];
                        break;
                }
            }
            if (!empty($locationConditions)) {
                // Match by location first to reduce the dataset
                if (count($locationConditions) === 1) {
                    $pipeline[] = ['$match' => $locationConditions[0]];
                } else {
                    $pipeline[] = ['$match' => ['$or' => $locationConditions]];
                }
            }
        }

        // Step 3: Match by entity (attackers and victims)
        if (!empty($entities)) {
            $entityConditions = [];
            foreach ($entities as $entity) {
                $id = $entity['id'];
                $type = $entity['type'];
                $treatment = $entity['treatment'];

                switch ($treatment) {
                    case 'friend':
                        $entityConditions[] = ['attackers.' . $type . '_id' => $id];
                        break;
                    case 'foe':
                        $entityConditions[] = ['victim.' . $type . '_id' => $id];
                        break;
                    case 'solo':
                        $entityConditions[] = [
                            '$or' => [
                                ['victim.' . $type . '_id' => $id],
                                ['attackers.' . $type . '_id' => $id]
                            ],
                            'is_solo' => true
                        ];
                        break;
                    case 'npc':
                        $entityConditions[] = [
                            '$or' => [
                                ['victim.' . $type . '_id' => $id],
                                ['attackers.' . $type . '_id' => $id]
                            ],
                            'is_npc' => true
                        ];
                        break;
                }
            }
            if (!empty($entityConditions)) {
                // Separate match for entities to take advantage of possible indexed fields
                if (count($entityConditions) === 1) {
                    $pipeline[] = ['$match' => $entityConditions[0]];
                } else {
                    $pipeline[] = ['$match' => ['$or' => $entityConditions]];
                }
            }
        }

        // Optional: Use $project to only retrieve necessary fields
        $pipeline[] = [
            '$project' => [
                'kill_time' => 1,
                'system_id' => 1,
                'region_id' => 1,
                'attackers' => 1,
                'victim' => 1,
                'value_killed' => 1,
                'value_lost' => 1
            ]
        ];

        // Fetch the killmails using a MongoDB cursor instead of loading everything into memory
        $cursor = $this->killmails->collection->aggregate($pipeline);

        // Initialize stats
        $kills = 0;
        $losses = 0;
        $totalValueKilled = 0;
        $totalValueLost = 0;
        $activePilots = [];
        $systemActivity = [];
        $regionActivity = [];
        $shipUsage = [];

        // Process each killmail in the cursor to compute stats
        foreach ($cursor as $killmail) {
            // Increment kills if attackers are present
            if (!empty($killmail['attackers'])) {
                $kills++;
                $totalValueKilled += $killmail['value_killed'] ?? 0;
            }

            // Increment losses if the victim is present
            if (!empty($killmail['victim'])) {
                $losses++;
                $totalValueLost += $killmail['value_lost'] ?? 0;
            }

            // Add active pilots from attackers
            foreach ($killmail['attackers'] as $attacker) {
                if (isset($attacker['character_id'])) {
                    $activePilots[$attacker['character_id']] = true; // Use associative array to ensure uniqueness
                }
            }

            // Track system activity
            if (!empty($killmail['system_id'])) {
                if (!isset($systemActivity[$killmail['system_id']])) {
                    $systemActivity[$killmail['system_id']] = 0;
                }
                $systemActivity[$killmail['system_id']]++;
            }

            // Track region activity
            if (!empty($killmail['region_id'])) {
                if (!isset($regionActivity[$killmail['region_id']])) {
                    $regionActivity[$killmail['region_id']] = 0;
                }
                $regionActivity[$killmail['region_id']]++;
            }

            // Track ship usage from attackers
            foreach ($killmail['attackers'] as $attacker) {
                if (!empty($attacker['ship_id'])) {
                    if (!isset($shipUsage[$attacker['ship_id']])) {
                        $shipUsage[$attacker['ship_id']] = 0;
                    }
                    $shipUsage[$attacker['ship_id']]++;
                }
            }
        }

        // Post-processing the stats
        $campaign['stats'] = [
            'kills' => $kills,
            'losses' => $losses,
            'value_killed' => $totalValueKilled,
            'value_lost' => $totalValueLost,
            'active_pilots' => count($activePilots), // Unique active pilots
            'most_active_system' => !empty($systemActivity) ? array_search(max($systemActivity), $systemActivity) : null,
            'most_active_region' => !empty($regionActivity) ? array_search(max($regionActivity), $regionActivity) : null,
            'most_used_ship' => !empty($shipUsage) ? array_search(max($shipUsage), $shipUsage) : null,
        ];

        return $campaign;
    }


}
