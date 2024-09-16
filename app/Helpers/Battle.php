<?php

namespace EK\Helpers;

use EK\Models\Killmails;
use EK\Models\SolarSystems;
use MongoDB\BSON\UTCDateTime;

class Battle
{
    public function __construct(
        protected Killmails $killmails,
        protected SolarSystems $solarSystems
    ) {

    }

    public function isKillInBattle(int $killmail_id): ?bool
    {
        $killmail = $this->killmails->findOneOrNull(
            ['killmail_id' => $killmail_id],
            ['projection' => ['_id' => 0, 'system_id' => 1, 'kill_time' => 1]]
        );

        if ($killmail === null) {
            return null;
        }

        /** @var UTCDateTime $killTime */
        $killTime = $killmail['kill_time'];
        $systemId = $killmail['system_id'];

        $startTime = $killTime->toDateTime()->getTimestamp() - 3600;
        $endTime = $killTime->toDateTime()->getTimestamp() + 3600;

        $pipeline = [
            ['$match' => ['system_id' => $systemId, 'kill_time' => ['$gte' => new UTCDateTime($startTime * 1000), '$lte' => new UTCDateTime($endTime * 1000)]]],
            ['$group' => ['_id' => '$battle_id', 'count' => ['$sum' => 1]]],
            ['$match' => ['count' => ['$gte' => 25]]],
            ['$sort' => ['count' => -1]],
            ['$limit' => 1]
        ];

        $battleData = iterator_to_array($this->killmails->aggregate($pipeline));

        if (count($battleData) === 0) {
            return false;
        }
        return true;
    }

    public function getBattleData(int $killmail_id): array
    {
        $killmail = $this->killmails->findOneOrNull(['killmail_id' => $killmail_id], ['projection' => ['_id' => 0, 'system_id' => 1, 'kill_time' => 1]]);
        if ($killmail === null) {
            return [];
        }

        /** @var UTCDateTime $killTime */
        $killTime = $killmail['kill_time'];
        $systemId = $killmail['system_id'];
        $startTime = $killTime->toDateTime()->getTimestamp() - 3600;
        $endTime = $killTime->toDateTime()->getTimestamp() + 3600;

        $extensibleToTime = $endTime;
        $segmentStart = $startTime;
        $segmentEnd = $startTime + 300;
        $foundStart = false;
        $foundEnd = false;
        $battleStartTime = 0;
        $battleEndTime = 0;
        $failCounter = 0;
        $killCountToConsiderStart = 5;
        $killCountToConsiderEnd = 15;

        do {
            $killCount = $this->killmails->count([
                'kill_time' => ['$gte' => new UTCDateTime($segmentStart * 1000), '$lte' => new UTCDateTime($segmentEnd * 1000)],
                'system_id' => $systemId
            ]);

            if ($killCount >= $killCountToConsiderStart) {
                if (!$foundStart) {
                    $foundStart = true;
                    $battleStartTime = $segmentStart;
                }
                $failCounter = 0;
            } else {
                if ($failCounter >= 3) {
                    $foundEnd = true;
                    $battleEndTime = $segmentStart;
                }
                $failCounter++;
            }

            // We can _ONLY_ extend $toTime by 1h if we hit >5 kills in the last 5 minute segment
            if ($segmentEnd >= $extensibleToTime && $killCount >= $killCountToConsiderEnd) {
                $extensibleToTime += 1600;
            }

            $segmentStart += 300;
            $segmentEnd += 300;
        } while ($segmentEnd < $extensibleToTime);

        if ($foundStart && $foundEnd) {
            if ($battleEndTime < $battleStartTime) {
                return [];
            }
            return $this->processBattle($systemId, $battleStartTime, $battleEndTime);
        }
        return [];
    }

    public function processBattle(int $systemId, int $battleStartTime, int $battleEndTime): array
    {
        $kills = iterator_to_array($this->killmails->aggregate([
            ['$match' => [
                'kill_time' => ['$gte' => new UTCDateTime($battleStartTime * 1000), '$lte' => new UTCDateTime($battleEndTime * 1000)],
                'system_id' => $systemId
            ]],
            ['$project' => [
                '_id' => 0,
                'items' => 0,
            ]],
        ], ['hint' => 'kill_time_system_id']));

        // Find the teams
        $teams = $this->findTeams($kills);
        $redTeam = $teams['a'];
        $blueTeam = $teams['b'];

        // Total stats
        $battle = [
            'start_time' => $battleStartTime,
            'end_time' => $battleEndTime,
            'system_id' => $systemId,
            'systemInfo' => $this->solarSystems->findOne(['system_id' => $systemId], ['projection' => [
                '_id' => 0,
                'planets' => 0,
                'position' => 0,
                'stargates' => 0,
                'stations' => 0,
                'last_modified' => 0
            ]]),
            'red_team' => $redTeam,
            'blue_team' => $blueTeam
        ];

        // Sort the battle array, but keep redTeam and blueTeam at the very bottom
        uksort($battle, function($a, $b) {
            if ($a === 'red_team' || $a === 'blue_team') {
                return 1;
            }
            if ($b === 'red_team' || $b === 'blue_team') {
                return -1;
            }
            return $a <=> $b;
        });

        return $battle;
    }

    private function findTeams(array $killmails): array
    {
        $attackMatrix = [];
        $corporationNames = [];
        $allianceNames = [];
        $corporationAlliances = [];

        // Build the attack matrix and store names and alliances
        foreach ($killmails as $killmail) {
            $victim = $killmail['victim'];
            $attackers = $killmail['attackers'];
            $totalDamage = $victim['damage_taken'];

            foreach ($attackers as $attacker) {
                // Only consider attackers who did at least 10% of the total damage
                if ($attacker['damage_done'] < 0.05 * $totalDamage) {
                    continue;
                }

                // Initialize the matrix if not already
                if (!isset($attackMatrix[$victim['corporation_id']])) {
                    $attackMatrix[$victim['corporation_id']] = [];
                }
                if (!isset($attackMatrix[$victim['corporation_id']][$attacker['corporation_id']])) {
                    $attackMatrix[$victim['corporation_id']][$attacker['corporation_id']] = 0;
                }
                $attackMatrix[$victim['corporation_id']][$attacker['corporation_id']]++;

                // Store corporation and alliance names
                if (!isset($corporationNames[$victim['corporation_id']])) {
                    $corporationNames[$victim['corporation_id']] = $victim['corporation_name'];
                }
                if ($victim['alliance_id'] != 0 && !isset($allianceNames[$victim['alliance_id']])) {
                    $allianceNames[$victim['alliance_id']] = $victim['alliance_name'];
                    $corporationAlliances[$victim['corporation_id']] = $victim['alliance_id'];
                }

                if (!isset($corporationNames[$attacker['corporation_id']])) {
                    $corporationNames[$attacker['corporation_id']] = $attacker['corporation_name'];
                }
                if ($attacker['alliance_id'] != 0 && !isset($allianceNames[$attacker['alliance_id']])) {
                    $allianceNames[$attacker['alliance_id']] = $attacker['alliance_name'];
                    $corporationAlliances[$attacker['corporation_id']] = $attacker['alliance_id'];
                }
            }
        }

        // Determine the teams
        $teams = $this->determineTeams($attackMatrix, $corporationAlliances, $corporationNames, $allianceNames);

        // Add the names of corporations and alliances
        foreach ($teams as &$team) {
            foreach ($team['corporations'] as &$corporation) {
                $corporation = [
                    'id' => $corporation,
                    'name' => $corporationNames[$corporation]
                ];
            }
            foreach ($team['alliances'] as &$alliance) {
                $alliance = [
                    'id' => $alliance,
                    'name' => $allianceNames[$alliance]
                ];
            }
        }

        return $teams;
    }

    private function determineTeams(array $attackMatrix, array $corporationAlliances, $corporationNames, $allianceNames): array
    {
        $teams = ['a' => ['corporations' => [], 'alliances' => []], 'b' => ['corporations' => [], 'alliances' => []]];
        $assignedCorporations = [];
        $assignedAlliances = [];
        $interactionCounts = [];

        foreach ($attackMatrix as $victimCorp => $attackers) {
            foreach ($attackers as $attackerCorp => $count) {
                if (!isset($interactionCounts[$victimCorp])) {
                    $interactionCounts[$victimCorp] = [];
                }
                if (!isset($interactionCounts[$victimCorp][$attackerCorp])) {
                    $interactionCounts[$victimCorp][$attackerCorp] = 0;
                }
                $interactionCounts[$victimCorp][$attackerCorp] += $count;

                $victimAlliance = $corporationAlliances[$victimCorp] ?? null;
                $attackerAlliance = $corporationAlliances[$attackerCorp] ?? null;

                if (!isset($assignedCorporations[$victimCorp]) && !isset($assignedCorporations[$attackerCorp])) {
                    $teams['a']['corporations'][] = $victimCorp;
                    $teams['b']['corporations'][] = $attackerCorp;
                    $assignedCorporations[$victimCorp] = 'a';
                    $assignedCorporations[$attackerCorp] = 'b';

                    if ($victimAlliance && !in_array($victimAlliance, $teams['a']['alliances'])) {
                        $teams['a']['alliances'][] = $victimAlliance;
                        $assignedAlliances[$victimAlliance] = 'a';
                    }
                    if ($attackerAlliance && !in_array($attackerAlliance, $teams['b']['alliances'])) {
                        $teams['b']['alliances'][] = $attackerAlliance;
                        $assignedAlliances[$attackerAlliance] = 'b';
                    }
                } elseif (isset($assignedCorporations[$victimCorp]) && !isset($assignedCorporations[$attackerCorp])) {
                    $oppositeTeam = ($assignedCorporations[$victimCorp] == 'a') ? 'b' : 'a';
                    $teams[$oppositeTeam]['corporations'][] = $attackerCorp;
                    $assignedCorporations[$attackerCorp] = $oppositeTeam;

                    if ($attackerAlliance && !isset($assignedAlliances[$attackerAlliance])) {
                        $teams[$oppositeTeam]['alliances'][] = $attackerAlliance;
                        $assignedAlliances[$attackerAlliance] = $oppositeTeam;
                    }
                } elseif (!isset($assignedCorporations[$victimCorp]) && isset($assignedCorporations[$attackerCorp])) {
                    $oppositeTeam = ($assignedCorporations[$attackerCorp] == 'a') ? 'b' : 'a';
                    $teams[$oppositeTeam]['corporations'][] = $victimCorp;
                    $assignedCorporations[$victimCorp] = $oppositeTeam;

                    if ($victimAlliance && !isset($assignedAlliances[$victimAlliance])) {
                        $teams[$oppositeTeam]['alliances'][] = $victimAlliance;
                        $assignedAlliances[$victimAlliance] = $oppositeTeam;
                    }
                }
            }
        }

        return $teams;
    }
}
