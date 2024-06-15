<?php

namespace EK\Commands\Battles;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Models\Battles;
use EK\Models\Corporations;
use EK\Models\Killmails;
use EK\Models\SolarSystems;
use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;

class BackfillBattles extends ConsoleCommand
{
    protected string $signature = 'battles:backfill {--starttime=} {--endtime=}';
    protected string $description = 'Backfill battles';

    public function __construct(
        protected Killmails $killmails,
        protected Battles $battles,
        protected SolarSystems $solarSystems,
        protected Corporations $corporations,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        $times = $this->getStartAndEndTime();
        $startTime = $times['unixTime']['startTime'];
        $endTime = $times['unixTime']['endTime'];
        $formattedStartTime = $times['formattedTime']['startTime'];
        $formattedEndTime = $times['formattedTime']['endTime'];

        $this->out("Backfilling battles from {$formattedStartTime} to {$formattedEndTime}");

        $fromTime = $startTime - 7200; // - 3600;
        $toTime = $startTime; // + 3600;
        do {
            //$this->out('Looking for battles between ' . date('Y-m-d H:i:s', $fromTime) . ' and ' . date('Y-m-d H:i:s', $toTime));
            $pipeline = [
                ['$match' => [
                    'kill_time' => ['$gte' => new UTCDateTime($fromTime * 1000), '$lte' => new UTCDateTime($toTime * 1000)]
                ]],
                ['$group' => [
                    '_id' => '$system_id',
                    'count' => ['$sum' => 1]
                ]],
                ['$match' => [
                    'count' => ['$gt' => 25]
                ]],
                ['$sort' => ['count' => -1]]
            ];

            $potentialBattles = $this->killmails->aggregate($pipeline, ['hint' => 'kill_time']);

            // This is where we start looking for the battle in 5 minute segments
            if ($potentialBattles->count() > 0) {
                foreach($potentialBattles as $potentialBattle) {
                    $systemId = $potentialBattle['_id'];
                    //$this->out("Potential battle in system {$systemId}");

                    $extensibleToTime = $toTime;
                    $segmentStart = $fromTime;
                    $segmentEnd = $fromTime + 300;
                    $foundStart = false;
                    $foundEnd = false;
                    $battleStartTime = 0;
                    $battleEndTime = 0;
                    $failCounter = 0;
                    $killCountToConsider = 25;

                    do {
                        $killCount = $this->killmails->count([
                            'kill_time' => ['$gte' => new UTCDateTime($segmentStart * 1000), '$lte' => new UTCDateTime($segmentEnd * 1000)],
                            'system_id' => $systemId
                        ]);

                        //$this->out("Segment between " . date('Y-m-d H:i:s', $segmentStart) . " and " . date('Y-m-d H:i:s', $segmentEnd) . " has {$killCount} kills");

                        if ($killCount >= $killCountToConsider) {
                            if (!$foundStart) {
                                $foundStart = true;
                                $battleStartTime = $segmentStart;
                            }
                            $failCounter = 0;
                        } else {
                            if ($failCounter >= 5) {
                                $foundEnd = true;
                                $battleEndTime = $segmentStart;
                            }
                            $failCounter++;
                        }

                        // We can _ONLY_ extend $toTime by 1h if we hit >5 kills in the last 5 minute segment
                        if ($segmentEnd >= $extensibleToTime && $killCount >= $killCountToConsider) {
                            $this->out("Extending toTime by 30m because we hit >5 kills in the last 5 minute segment");
                            $extensibleToTime += 1600;
                        }

                        $segmentStart += 300;
                        $segmentEnd += 300;
                    } while ($segmentEnd < $extensibleToTime);

                    if ($foundStart && $foundEnd) {
                        $this->out('Found a battle in system ' . $systemId . ' between ' . date('Y-m-d H:i:s', $battleStartTime) . ' and ' . date('Y-m-d H:i:s', $battleEndTime));
                        if ($battleEndTime < $battleStartTime) {
                            $this->out('Battle end time is before start time, skipping');
                            continue;
                        }
                        $this->processBattle($systemId, $battleStartTime, $battleEndTime);
                    }
                }
            }

            $fromTime += 7200;
            $toTime += 7200;
        } while($fromTime < $endTime);
    }

    private function processBattle(int $systemId, int $battleStartTime, int $battleEndTime): void
    {
        $kills = $this->killmails->aggregate([
            ['$match' => [
                'kill_time' => ['$gte' => new UTCDateTime($battleStartTime * 1000), '$lte' => new UTCDateTime($battleEndTime * 1000)],
                'system_id' => $systemId
            ]],
            ['$project' => [
                '_id' => 0,
                'items' => 0,
            ]],
            ['$unwind' => '$attackers']
        ], ['hint' => 'kill_time_system_id']);

        // Find the teams
        $teams = $this->findTeams($kills);
        $redTeam = $teams['a'];
        $blueTeam = $teams['b'];

        // Populate the battle data
        foreach ($redTeam['corporations'] as $teamMember) {
            // teamMember has both corporation and alliance arrays
            foreach($kills as $kill) {
                if ($kill['attackers']['corporation_id'] === $teamMember['id']) {
                    if (!isset($redTeam['kills'])) {
                        $redTeam['kills'] = [];
                    }
                    if (!isset($redTeam['value'])) {
                        $redTeam['value'] = 0;
                    }
                    if (!isset($redTeam['points'])) {
                        $redTeam['points'] = 0;
                    }

                    $redTeam['kills'][$kill['killmail_id']] = $kill['killmail_id'];
                    $redTeam['value'] += $kill['total_value'];
                    $redTeam['points'] += $kill['point_value'];

                    // Add the ship type to the team, if it's already added then increment the count
                    if (!isset($redTeam['ship_types'][$kill['attackers']['ship_id']])) {
                        $redTeam['ship_types'][$kill['attackers']['ship_id']] = [
                            'name' => $kill['attackers']['ship_name'],
                            'count' => 1
                        ];
                    } else {
                        $redTeam['ship_types'][$kill['attackers']['ship_id']]['count']++;
                    }

                    // Add the character to the team (if not already added)
                    if (!isset($redTeam['characters'][$kill['attackers']['character_id']])) {
                        $redTeam['characters'][$kill['attackers']['character_id']] = [
                            'character_name' => $kill['attackers']['character_name'],
                            'character_id' => $kill['attackers']['character_id'],
                            'corporation_name' => $kill['attackers']['corporation_name'],
                            'corporation_id' => $kill['attackers']['corporation_id'],
                            'alliance_name' => $kill['attackers']['alliance_name'],
                            'alliance_id' => $kill['attackers']['alliance_id'],
                            'faction_name' => $kill['attackers']['faction_name'],
                            'faction_id' => $kill['attackers']['faction_id']
                        ];
                    }
                }
            }
            $redTeam['ship_type_count'] = count($redTeam['ship_types'] ?? []);
            $redTeam['total_ship_count'] = array_sum(array_column($redTeam['ship_types'] ?? [], 'count'));
            ksort($redTeam);
        }

        // Sort the ship types by count
        if (!empty($redTeam['ship_types'])) {
            uasort($redTeam['ship_types'], function ($a, $b) {
                return $b['count'] <=> $a['count'];
            });
        }

        // Blue team
        foreach ($blueTeam['corporations'] as $teamMember) {
            // teamMember has both corporation and alliance arrays
            foreach($kills as $kill) {
                if ($kill['attackers']['corporation_id'] === $teamMember['id']) {
                    if (!isset($blueTeam['kills'])) {
                        $blueTeam['kills'] = [];
                    }
                    if (!isset($blueTeam['value'])) {
                        $blueTeam['value'] = 0;
                    }
                    if (!isset($blueTeam['points'])) {
                        $blueTeam['points'] = 0;
                    }

                    $blueTeam['kills'][$kill['killmail_id']] = $kill['killmail_id'];
                    $blueTeam['value'] += $kill['total_value'];
                    $blueTeam['points'] += $kill['point_value'];

                    // Add the ship type to the team, if it's already added then increment the count
                    if (!isset($blueTeam['ship_types'][$kill['attackers']['ship_id']])) {
                        $blueTeam['ship_types'][$kill['attackers']['ship_id']] = [
                            'name' => $kill['attackers']['ship_name'],
                            'count' => 1
                        ];
                    } else {
                        $blueTeam['ship_types'][$kill['attackers']['ship_id']]['count']++;
                    }

                    // Add the character to the team (if not already added)
                    if (!isset($blueTeam['characters'][$kill['attackers']['character_id']])) {
                        $blueTeam['characters'][$kill['attackers']['character_id']] = [
                            'character_name' => $kill['attackers']['character_name'],
                            'character_id' => $kill['attackers']['character_id'],
                            'corporation_name' => $kill['attackers']['corporation_name'],
                            'corporation_id' => $kill['attackers']['corporation_id'],
                            'alliance_name' => $kill['attackers']['alliance_name'],
                            'alliance_id' => $kill['attackers']['alliance_id'],
                            'faction_name' => $kill['attackers']['faction_name'],
                            'faction_id' => $kill['attackers']['faction_id']
                        ];
                    }
                }
            }
            $blueTeam['ship_type_count'] = count($blueTeam['ship_types']);
            $blueTeam['total_ship_count'] = array_sum(array_column($blueTeam['ship_types'], 'count'));
            ksort($blueTeam);
        }

        // Sort the ship types by count
        if (!empty($blueTeam['ship_types'])) {
            uasort($blueTeam['ship_types'], function ($a, $b) {
                return $b['count'] <=> $a['count'];
            });
        }

        // Kills can sometime show up in both blue and red team, remove them from blue team if they do
        $blueTeam['kills'] = array_diff($blueTeam['kills'] ?? [], $redTeam['kills'] ?? []);

        // Total stats
        $battle = [
            'start_time' => new UTCDateTime($battleStartTime * 1000),
            'end_time' => new UTCDateTime($battleEndTime * 1000),
            'total_value' => $redTeam['value'] ?? 0 + $blueTeam['value'] ?? 0,
            'total_alliances' => count($redTeam['alliances'] ?? []) + count($blueTeam['alliances'] ?? []),
            'total_corporations' => count($redTeam['corporations'] ?? []) + count($blueTeam['corporations'] ?? []),
            'total_characters' => count($redTeam['characters'] ?? []) + count($blueTeam['characters'] ?? []),
            'ship_type_count' => $redTeam['ship_type_count'] ?? + + $blueTeam['ship_type_count'] ?? 0,
            'ship_count' => $redTeam['total_ship_count'] ?? 0 + $blueTeam['total_ship_count'] ?? 0,
            'points' => $redTeam['points'] ?? 0 + $blueTeam['points'] ?? 0,
            'kills' => count(array_unique(array_column($kills->toArray() ?? [], 'killmail_id'))),
            'kills_team_count' => count($redTeam['kills'] ?? []) + count($blueTeam['kills'] ?? []),
            'red_team' => $redTeam,
            'blue_team' => $blueTeam
        ];

        // Insert the battle into the database
        $battleId = md5(json_encode($battle, JSON_THROW_ON_ERROR, 512));
        $battle['battle_id'] = $battleId;

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

        // Save to the db
        $this->battles->setData($battle);
        $this->battles->save();
    }

    private function findTeams(Collection $killmails): array
    {
        $attackMatrix = [];
        $corporationNames = [];
        $allianceNames = [];
        $corporationAlliances = [];

        // Build the attack matrix and store names and alliances
        foreach ($killmails as $killmail) {
            $victim = $killmail['victim'];
            $attacker = $killmail['attackers'];

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

    private function getStartAndEndTime(): array
    {
        // Find the earliest killmail and get it's kill_time
        $earliestKillmail = $this->killmails->findOne([], ['hint' => 'kill_time', 'sort' => ['kill_time' => 1]]);
        /** @var UTCDateTime $earliestTime */
        $earliestTime = $earliestKillmail->get('kill_time');
        $calculatedStartTime = $earliestTime->toDateTime()->getTimestamp();
        $calculatedEndTime = time();

        // Get the command line options
        $inputStartTime = $this->starttime;
        $inputEndTime = $this->endtime;

        // If the options are not set, use the calculated times
        $startTime = $inputStartTime ? strtotime($inputStartTime) : $calculatedStartTime;
        $endTime = $inputEndTime ? strtotime($inputEndTime) : $calculatedEndTime;

        // Format the timestamps to 'Y-m-d H:i:s'
        $formattedStartTime = date('Y-m-d H:i:s', $startTime);
        $formattedEndTime = date('Y-m-d H:i:s', $endTime);

        return [
            'unixTime' => [
                'startTime' => $startTime,
                'endTime' => $endTime
            ],
            'formattedTime' => [
                'startTime' => $formattedStartTime,
                'endTime' => $formattedEndTime
            ]
        ];
    }
}
