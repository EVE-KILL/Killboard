<?php

namespace EK\Commands\Battles;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Models\Battles;
use EK\Models\Killmails;
use EK\Models\SolarSystems;
use MongoDB\BSON\UTCDateTime;

class BackfillBattles extends ConsoleCommand
{
    protected string $signature = 'battles:backfill {--starttime=} {--endtime=}';
    protected string $description = 'Backfill battles';

    public function __construct(
        protected Killmails $killmails,
        protected Battles $battles,
        protected SolarSystems $solarSystems,
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

        $searchTime = $startTime;
        do {
            $timeFrom = new UTCDateTime(($searchTime - 2700) * 1000);
            $timeTo = new UTCDateTime(($searchTime + 2700) * 1000);

            $pipeline = [
                ['$match' => ['kill_time' => ['$gte' => $timeFrom, '$lte' => $timeTo]]],
                ['$group' => ['_id' => '$system_id', 'count' => ['$sum' => 1]]],
                ['$project' => ['_id' => 0, 'count' => '$count', 'system_id' => '$_id']],
                ['$match' => ['count' => ['$gte' => 50]]],
                ['$sort' => ['count' => -1]]
            ];

            $results = $this->killmails->aggregate($pipeline);
            if ($results->count() >= 1) {
                foreach($results as $result) {
                    $run = true;
                    $fails = 0;
                    $minTime = $searchTime - 2700;
                    $systemId = $result['system_id'];
                    $battleStartTime = 0;

                    // Look if there are any battles already in the database for this system
                    //$battleCount = $this->battles->find(['system_id' => $systemId, 'start_time' => ['$gte' => new UTCDateTime(($minTime - 14400) * 1000), '$lte' => new UTCDateTime($searchTime * 1000)]]);

                    do {
                        $timeFrom = new UTCDateTime($minTime * 1000);
                        $timeTo = new UTCDateTime(($minTime + 600) * 1000);
                        $pipeline = [
                            ['$match' => ['system_id' => $systemId, 'kill_time' => ['$gte' => $timeFrom, '$lte' => $timeTo]]],
                            ['$group' => ['_id' => '$system_id', 'count' => ['$sum' => 1]]],
                            ['$project' => ['_id' => 0, 'count' => '$count', 'system_id' => '$_id']],
                            ['$match' => ['count' => ['$gte' => 3]]],
                            ['$sort' => ['count' => -1]],
                        ];

                        $result = $this->killmails->aggregate($pipeline);

                        if ($battleStartTime === 0 && $result->count() >= 1) {
                            $fails = 0;
                            $battleStartTime = $minTime;
                        }

                        if ($fails < 20 && $battleStartTime !== 0) {
                            $systemData = $this->solarSystems->findOne(['system_id' => $systemId]);
                            $systemName = $systemData->get('name');
                            $regionName = $systemData->get('region_name');
                            $this->processBattle($minTime - 12000, $battleStartTime, $systemId);
                            $run = false;
                        } elseif ($fails >= 20) {
                            $run = false;
                        }

                        $minTime += 600;
                        if ($result->count() === 0) {
                            $fails++;
                            continue;
                        }
                    } while ($run === true);
                }
            }

            $searchTime += 3600;
        } while($searchTime < $endTime);
    }

    private function processBattle(int $startTime, int $endTime, int $systemId): void
    {
        $data = $this->killmails->aggregate([
            ['$match' => ['system_id' => $systemId, 'kill_time' => ['$gte' => new UTCDateTime($startTime * 1000), '$lte' => new UTCDateTime($endTime * 1000)]]],
            ['$unwind' => '$attackers']
        ]);

        $teams = $this->findTeams($data);
        $redTeam = $teams['redTeam'];
        $blueTeam = $teams['blueTeam'];

        $redTeamCharacters = [];
        $redTeamShips = [];
        $redTeamCorporations = [];
        $redTeamKills = [];
        $blueTeamCharacters = [];
        $blueTeamShips = [];
        $blueTeamCorporations = [];
        $blueTeamKills = [];

        foreach ($data as $mail) {
            foreach ($redTeam['alliances'] as $alliance) {
                if ($mail['attackers']['alliance_name'] === $alliance) {
                    $this->processTeamMember($mail, $redTeamCharacters, $redTeamCorporations, $redTeamShips, $redTeamKills);
                }
            }
            foreach ($redTeam['corporations'] as $corporation) {
                if ($mail['attackers']['corporation_name'] === $corporation) {
                    $this->processTeamMember($mail, $redTeamCharacters, $redTeamCorporations, $redTeamShips, $redTeamKills);
                }
            }
            foreach ($blueTeam['alliances'] as $alliance) {
                if ($mail['attackers']['alliance_name'] === $alliance) {
                    $this->processTeamMember($mail, $blueTeamCharacters, $blueTeamCorporations, $blueTeamShips, $blueTeamKills);
                }
            }
            foreach ($blueTeam['corporations'] as $corporation) {
                if ($mail['attackers']['corporation_name'] === $corporation) {
                    $this->processTeamMember($mail, $blueTeamCharacters, $blueTeamCorporations, $blueTeamShips, $blueTeamKills);
                }
            }
        }

        // Remove the overlap
        $this->removeOverlap($redTeamKills, $blueTeamKills);

        if (!empty($redTeam) && !empty($blueTeam) && !empty($redTeamCorporations) && !empty($blueTeamCorporations)) {
            $systemData = $this->solarSystems->findOne(['system_id' => $systemId], ['projection' => ['_id' => 0, 'planets' => 0, 'stargates' => 0]]);
            $dataArray = [
                'start_time' => new UTCDateTime($startTime * 1000),
                'end_time' => new UTCDateTime($endTime * 1000),
                'system_id' => $systemId,
                'system_name' => $systemData->get('name'),
                'region_id' => $systemData->get('region_id'),
                'region_name' => $systemData->get('region_name'),
                'teamRed' => [
                    'characters' => array_values($redTeamCharacters),
                    'corporations' => array_values($redTeamCorporations),
                    'alliances' => array_values($redTeam['alliances']),
                    'ships' => array_values($redTeamShips),
                    'kills' => array_values($redTeamKills),
                ],
                'teamBlue' => [
                    'characters' => array_values($blueTeamCharacters),
                    'corporations' => array_values($blueTeamCorporations),
                    'alliances' => array_values($blueTeam['alliances']),
                    'ships' => array_values($blueTeamShips),
                    'kills' => array_values($blueTeamKills),
                ],
            ];

            $battleID = md5(json_encode($dataArray, JSON_THROW_ON_ERROR, 512));
            $dataArray['battle_id'] = $battleID;

            // Emit to terminal we found a battle
            $this->out("Found a battle in {$systemData->get('name')} ({$systemData->get('region_name')}) at " . date('Y-m-d H:i:s', $startTime) . " with ID: {$battleID}");
            // Insert the data to the battles table
            $this->battles->setData($dataArray);
            $this->battles->save();
        }
    }

    private function processTeamMember($mail, &$teamCharacters, &$teamCorporations, &$teamShips, &$teamKills): void
    {
        if ($mail['attackers']['corporation_name'] !== '' && !in_array($mail['attackers']['corporation_name'], $teamCorporations, false)) {
            $teamCorporations[] = $mail['attackers']['corporation_name'];
        }

        if ($mail['attackers']['character_name'] !== '' && !in_array($mail['attackers']['character_name'], $teamCharacters, false)) {
            if (!isset($teamShips[$mail['attackers']['ship_name']])) {
                $teamShips[$mail['attackers']['ship_name']] = [
                    'ship_name' => $mail['attackers']['ship_name'],
                    'count' => 1,
                ];
            } else {
                $teamShips[$mail['attackers']['ship_name']]['count']++;
            }

            $teamCharacters[] = $mail['attackers']['character_name'];
        }

        if (!in_array($mail['killmail_id'], $teamKills, false)) {
            $teamKills[] = $mail['killmail_id'];
        }
    }

    private function removeOverlap(&$redTeamKills, &$blueTeamKills): void
    {
        if (count($blueTeamKills) > count($redTeamKills)) {
            foreach ($blueTeamKills as $key => $id) {
                if (in_array($id, $redTeamKills, false)) {
                    unset($blueTeamKills[$key]);
                }
            }
        } else {
            foreach ($redTeamKills as $key => $id) {
                if (in_array($id, $blueTeamKills, false)) {
                    unset($redTeamKills[$key]);
                }
            }
        }
    }

    protected function findTeams($killData): array
    {
        // Find alliances and corporations
        $alliances = $this->allianceSides($killData);
        $corporations = $this->corporationSides($killData);

        // If no alliances or corporations found, return empty teams
        if (empty($alliances['red']) || empty($alliances['blue']) || empty($corporations['red']) || empty($corporations['blue'])) {
            return ['redTeam' => ['corporations' => [], 'alliances' => []], 'blueTeam' => ['corporations' => [], 'alliances' => []]];
        }

        // Determine the teams based on the size of alliances and corporations
        $redTeam = count($alliances['red']) > count($corporations['red']) ? ['alliances' => $alliances['red'], 'corporations' => $corporations['red']] : ['alliances' => $alliances['red'], 'corporations' => $corporations['red']];
        $blueTeam = count($alliances['blue']) > count($corporations['blue']) ? ['alliances' => $alliances['blue'], 'corporations' => $corporations['blue']] : ['alliances' => $alliances['blue'], 'corporations' => $corporations['blue']];

        // Ensure a member only appears once and only on one team
        $redTeam['corporations'] = array_values(array_unique($redTeam['corporations']));
        $redTeam['alliances'] = array_values(array_unique($redTeam['alliances']));
        $blueTeam['corporations'] = array_values(array_unique($blueTeam['corporations']));
        $blueTeam['alliances'] = array_values(array_unique($blueTeam['alliances']));

        $redTeam['corporations'] = array_values(array_diff($redTeam['corporations'], $blueTeam['corporations']));
        $redTeam['alliances'] = array_values(array_diff($redTeam['alliances'], $blueTeam['alliances']));
        $blueTeam['corporations'] = array_values(array_diff($blueTeam['corporations'], $redTeam['corporations']));
        $blueTeam['alliances'] = array_values(array_diff($blueTeam['alliances'], $redTeam['alliances']));

        return ['redTeam' => $redTeam, 'blueTeam' => $blueTeam];
    }

    protected function allianceSides($killData): array
    {
        $allianceTemp = [];

        foreach ($killData as $data) {
            $attacker = $data['attackers'];
            $victim = $data['victim'];

            if (isset($attacker['alliance_name'], $victim['alliance_name']) && $victim['alliance_name'] !== '') {
                $allianceTemp[$victim['alliance_name']][] = $attacker['alliance_name'];
            }
        }

        foreach ($allianceTemp as $key => $alliances) {
            $allianceTemp[$key] = array_filter($alliances, function($alliance) {
                return $alliance !== '';
            });
            $allianceTemp[$key] = array_unique($allianceTemp[$key]);
        }

        $red = !empty($allianceTemp) ? max($allianceTemp) : [];
        $blue = !empty($allianceTemp) ? min($allianceTemp) : [];

        return ['red' => $red, 'blue' => $blue];
    }

    protected function corporationSides($killData): array
    {
        $corporationTemp = [];

        foreach ($killData as $data) {
            $attacker = $data['attackers'];
            $victim = $data['victim'];

            if (isset($attacker['corporation_name'], $victim['corporation_name']) && $victim['corporation_name'] !== '') {
                $corporationTemp[$victim['corporation_name']][] = $attacker['corporation_name'];
            }
        }

        foreach ($corporationTemp as $key => $corporations) {
            $corporationTemp[$key] = array_filter($corporations, function($corporation) {
                return $corporation !== '';
            });
            $corporationTemp[$key] = array_unique($corporationTemp[$key]);
        }

        $red = !empty($corporationTemp) ? max($corporationTemp) : [];
        $blue = !empty($corporationTemp) ? min($corporationTemp) : [];

        return ['red' => $red, 'blue' => $blue];
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
