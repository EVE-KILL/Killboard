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

    public function generateCampaignStats(string $campaignId): array
    {
        // Find the campaign to process
        $campaign = $this->campaigns->findOne(['campaign_id' => $campaignId], [
            'projection' => [
                '_id' => 0,
                'last_modified' => 0,
                'user' => 0
            ]
        ])->toArray();

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
                'total_value' => 1
            ]
        ];

        // Fetch the killmails using a MongoDB cursor instead of loading everything into memory
        $cursor = $this->killmails->collection->aggregate($pipeline);

        // Initialize stats
        $totalKills = 0;
        // Kills
        $friendlyKills = 0;
        $foeKills = 0;
        // Losses
        $friendlyLosses = 0;
        $foeLosses = 0;
        // Total Value
        $totalValue = 0;
        $friendlyTotalValue = 0;
        $foeTotalValue = 0;
        // Pilots
        $friendlyActivePilots = [];
        $foeActivePilots = [];
        // Corporations
        $friendlyActiveCorporations = [];
        $foeActiveCorporations = [];
        // Alliances
        $friendlyActiveAlliances = [];
        $foeActiveAlliances = [];
        // Systems
        $activeSystems = [];
        $friendlyActiveSystems = [];
        $foeActiveSystems = [];
        // Regions
        $activeRegions = [];
        $friendlyActiveRegions = [];
        $foeActiveRegions = [];
        // Ships
        $usedShips = [];
        $friendlyUsedShips = [];
        $foeUsedShips = [];
        // Ship Gruops
        $usedShipGroups = [];
        $friendlyUsedShipGroups = [];
        $foeUsedShipGroups = [];
        // Specialties
        $soloKills = 0;
        $npcKills = 0;

        // Process each killmail in the cursor to compute stats
        foreach ($cursor as $killmail) {
            // Every kill goes into $totalKills
            $totalKills++;
            $totalValue += $killmail['total_value'];
            $activeSystems[$killmail['system_id']] = true;
            $activeRegions[$killmail['region_id']] = true;
            foreach($killmail['attackers'] as $attacker) {
                $usedShips[$attacker['ship_id']] = true;
                $usedShipGroups[$attacker['ship_group_id']] = true;
            }
            $usedShips[$killmail['victim']['ship_id']] = true;
            $usedShipGroups[$killmail['victim']['ship_group_id']] = true;

            //
            if (!empty($entities)) {
                foreach ($entities as $entity) {
                    $id = $entity['id'];
                    $type = $entity['type'];
                    $treatment = $entity['treatment'];

                    switch ($treatment) {
                        case 'friend':
                                $friendlyKills++;
                                $friendlyTotalValue += $killmail['total_value'];
                                $friendlyActiveSystems[$killmail['system_id']] = true;
                                $friendlyActiveRegions[$killmail['region_id']] = true;

                                if ($killmail['victim'][$type . '_id'] === $id) {
                                    $friendlyLosses++;
                                }

                                foreach($killmail['attackers'] as $attacker) {
                                    switch($type . '_id') {
                                        case 'character_id':
                                            if ($attacker['character_id'] === $id) {
                                                $friendlyUsedShips[$attacker['ship_id']] = true;
                                                $friendlyUsedShipGroups[$attacker['ship_group_id']] = true;
                                            }
                                        break;
                                        case 'corporation_id':
                                            if ($attacker['corporation_id'] === $id) {
                                                $friendlyActivePilots[$attacker['character_id']] = true;
                                                $friendlyUsedShips[$attacker['ship_id']] = true;
                                                $friendlyUsedShipGroups[$attacker['ship_group_id']] = true;
                                            }
                                        break;
                                        case 'alliance_id':
                                            if ($attacker['alliance_id'] === $id) {
                                                $friendlyActivePilots[$attacker['character_id']] = true;
                                                $friendlyActiveCorporations[$attacker['corporation_id']] = true;
                                                $friendlyActiveAlliances[$attacker['alliance_id']] = true;
                                                $friendlyUsedShips[$attacker['ship_id']] = true;
                                                $friendlyUsedShipGroups[$attacker['ship_group_id']] = true;
                                            }
                                        break;
                                        case 'ship_id':
                                            if ($attacker['ship_id'] === $id) {
                                                $friendlyActivePilots[$attacker['character_id']] = true;
                                                $friendlyUsedShips[$attacker['ship_id']] = true;
                                                $friendlyUsedShipGroups[$attacker['ship_group_id']] = true;
                                            }
                                        break;
                                    }
                                }
                            break;
                        case 'foe':
                                $foeKills++;
                                $foeTotalValue += $killmail['total_value'];
                                $foeActiveSystems[$killmail['system_id']] = true;
                                $foeActiveRegions[$killmail['region_id']] = true;

                                if ($killmail['victim'][$type . '_id'] === $id) {
                                    $foeLosses++;
                                }

                                foreach($killmail['attackers'] as $attacker) {
                                    switch($type . '_id') {
                                        case 'character_id':
                                            if ($attacker['character_id'] === $id) {
                                                $foeUsedShips[$attacker['ship_id']] = true;
                                                $foeUsedShipGroups[$attacker['ship_group_id']] = true;
                                            }
                                        break;
                                        case 'corporation_id':
                                            if ($attacker['corporation_id'] === $id) {
                                                $foeActivePilots[$attacker['character_id']] = true;
                                                $foeUsedShips[$attacker['ship_id']] = true;
                                                $foeUsedShipGroups[$attacker['ship_group_id']] = true;
                                            }
                                        break;
                                        case 'alliance_id':
                                            if ($attacker['alliance_id'] === $id) {
                                                $foeActivePilots[$attacker['character_id']] = true;
                                                $foeActiveCorporations[$attacker['corporation_id']] = true;
                                                $foeActiveAlliances[$attacker['alliance_id']] = true;
                                                $foeUsedShips[$attacker['ship_id']] = true;
                                                $foeUsedShipGroups[$attacker['ship_group_id']] = true;
                                            }
                                        break;
                                        case 'ship_id':
                                            if ($attacker['ship_id'] === $id) {
                                                $foeActivePilots[$attacker['character_id']] = true;
                                                $foeUsedShips[$attacker['ship_id']] = true;
                                                $foeUsedShipGroups[$attacker['ship_group_id']] = true;
                                            }
                                        break;
                                    }
                                }
                            break;
                        case 'solo':
                            if ($killmail['is_solo']) {
                                $soloKills++;
                            }
                            break;
                        case 'npc':
                            if ($killmail['is_npc']) {
                                $npcKills++;
                            }
                            break;
                    }
                }
            }
        }

        // Post-processing the stats
        $campaign['stats'] = [
            'totalKills' => $totalKills,
            'friendlyKills' => $friendlyKills,
            'friendlyLosses' => $friendlyLosses,
            'foeKills' => $foeKills,
            'foeLosses' => $foeLosses,
            'totalValue' => $totalValue,
            'friendlyTotalValue' => $friendlyTotalValue,
            'foeTotalValue' => $foeTotalValue,
            'friendlyActivePilots' => count($friendlyActivePilots),
            'foeActivePilots' => count($foeActivePilots),
            'friendlyActiveCorporations' => count($friendlyActiveCorporations),
            'foeActiveCorporations' => count($foeActiveCorporations),
            'friendlyActiveAlliances' => count($friendlyActiveAlliances),
            'foeActiveAlliances' => count($foeActiveAlliances),
            'activeSystems' => count($activeSystems),
            'friendlyActiveSystems' => count($friendlyActiveSystems),
            'foeActiveSystems' => count($foeActiveSystems),
            'activeRegions' => count($activeRegions),
            'friendlyActiveRegions' => count($friendlyActiveRegions),
            'foeActiveRegions' => count($foeActiveRegions),
            'usedShips' => count($usedShips),
            'friendlyUsedShips' => count($friendlyUsedShips),
            'foeUsedShips' => count($foeUsedShips),
            'usedShipGroups' => count($usedShipGroups),
            'friendlyUsedShipGroups' => count($friendlyUsedShipGroups),
            'foeUsedShipGroups' => count($foeUsedShipGroups),
            'soloKills' => $soloKills,
            'npcKills' => $npcKills
        ];

        return $campaign;
    }
}
