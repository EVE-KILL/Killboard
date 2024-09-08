<?php

namespace EK\Helpers;

use EK\Models\Killmails;
use MongoDB\BSON\UTCDateTime;

class Stats
{
    public function __construct(
        protected Killmails $killmails
    ) {
    }

    public function calculateShortStats(string $type, int $id, int $timeRangeInDays = 90): array
    {
        $validTypes = ['character_id', 'corporation_id', 'alliance_id'];
        if (!in_array($type, $validTypes)) {
            throw new \Exception('Error, ' . $type . ' is not a valid type. Valid types are: ' . implode(', ', $validTypes));
        }

        if ($timeRangeInDays < 0) {
            $timeRangeInDays = 90;
        }

        // Initialize stats array
        $stats = [
            'kills' => 0,
            'losses' => 0,
            'iskKilled' => 0,
            'iskLost' => 0,
            'npcLosses' => 0,
            'soloKills' => 0,
            'soloLosses' => 0,
            'lastActive' => 0,
        ];

        // Get kill stats
        if ($timeRangeInDays > 0) {
            $stats['kills'] = $this->killmails->count([
                'attackers.' . $type => $id,
                'kill_time' => [
                    '$gte' => new UTCDateTime((time() - ($timeRangeInDays * 86400)) * 1000)
                ]
            ]);
        } else {
            $stats['kills'] = $this->killmails->count(['attackers.' . $type => $id]);
        }

        if ($timeRangeInDays > 0) {
            $stats['losses'] = $this->killmails->count([
                'victim.' . $type => $id,
                'kill_time' => [
                    '$gte' => new UTCDateTime((time() - ($timeRangeInDays * 86400)) * 1000)
                ]
            ]);
        } else {
            $stats['losses'] = $this->killmails->count(['victim.' . $type => $id]);
        }

        // Query for kills
        $attackerKillmails = $this->killmails->collection->find(
            $timeRangeInDays > 0 ? [
                'attackers.' . $type => $id,
                'kill_time' => [
                    '$gte' => new UTCDateTime((time() - ($timeRangeInDays * 86400)) * 1000)
                ]
             ] : ['attackers.' . $type => $id],
            ['projection' => [
                'total_value' => 1,
                'is_solo' => 1,
                'kill_time' => 1,
            ]
        ]);

        foreach ($attackerKillmails as $killmail) {
            $stats['iskKilled'] += $killmail['total_value'];
            $stats['soloKills'] += $killmail['is_solo'] ? 1 : 0;

            // Update lastActive with the latest kill_time
            $killTime = $killmail['kill_time'];
            if ($killTime instanceof UTCDateTime) {
                $killTime = $killTime->toDateTime(); // Convert MongoDB UTCDateTime to PHP DateTime
            }

            $killTimeUnix = $killTime->getTimestamp(); // Convert to Unix timestamp

            // Compare Unix timestamps and store the latest
            if ($killTimeUnix > $stats['lastActive']) {
                $stats['lastActive'] = $killTimeUnix;
            }
        }

        // Query for losses
        $victimKillmails = $this->killmails->collection->find(
            $timeRangeInDays > 0 ? [
                'victim.' . $type => $id,
                'kill_time' => [
                    '$gte' => new UTCDateTime((time() - ($timeRangeInDays * 86400)) * 1000)
                ]
             ] : ['victim.' . $type => $id],
            ['projection' => [
                'total_value' => 1,
                'is_npc' => 1,
                'is_solo' => 1,
                'kill_time' => 1,
            ]
        ]);

        foreach ($victimKillmails as $lossmail) {
            $stats['iskLost'] += $lossmail['total_value'];
            $stats['npcLosses'] += $lossmail['is_npc'] ? 1 : 0;
            $stats['soloLosses'] += $lossmail['is_solo'] ? 1 : 0;

            // Update lastActive with the latest kill_time
            $killTime = $lossmail['kill_time'];
            if ($killTime instanceof UTCDateTime) {
                $killTime = $killTime->toDateTime(); // Convert MongoDB UTCDateTime to PHP DateTime
            }

            $killTimeUnix = $killTime->getTimestamp(); // Convert to Unix timestamp

            // Compare Unix timestamps and store the latest
            if ($killTimeUnix > $stats['lastActive']) {
                $stats['lastActive'] = $killTimeUnix;
            }
        }

        // Convert lastActive from Unix timestamp to formatted string, if not zero
        if ($stats['lastActive'] > 0) {
            $stats['lastActive'] = date('Y-m-d H:i:s', $stats['lastActive']);
        } else {
            $stats['lastActive'] = null;
        }

        return $stats;
    }

    public function calculateFullStats(string $type, int $id, int $timeRangeInDays = 90): array
    {
        $validTypes = ['character_id', 'corporation_id', 'alliance_id'];
        if (!in_array($type, $validTypes)) {
            throw new \Exception('Error, ' . $type . ' is not a valid type. Valid types are: ' . implode(', ', $validTypes));
        }

        if ($timeRangeInDays < 0) {
            $timeRangeInDays = 90;
        }

        // Initialize heat map with string keys
        $heatMap = [];
        for ($i = 0; $i < 24; $i++) {
            $hourString = 'h' . str_pad((string)$i, 2, '0', STR_PAD_LEFT); // Convert hours to strings "00", "01", etc.
            $heatMap[$hourString] = 0;
        }

        // Initialize stats array
        $stats = [
            'kills' => 0,
            'losses' => 0,
            'iskKilled' => 0,
            'iskLost' => 0,
            'npcLosses' => 0,
            'soloKills' => 0,
            'soloLosses' => 0,
            'lastActive' => 0,
            'mostUsedShips' => [],
            'mostLostShips' => [],
            'diesToCorporations' => [],
            'diesToAlliances' => [],
            'blobFactor' => 0,
            'heatMap' => $heatMap, // Use the pre-initialized heatmap with string keys
            'fliesWithCorporations' => [],
            'fliesWithAlliances' => [],
            'sameShipAsOtherAttackers' => [],
            'whoreKills' => 0, // Kills where entity did less than 1% of total damage
            'possibleFC' => false,
            'possibleCynoAlt' => false,
        ];

        // Get kill stats
        if ($timeRangeInDays > 0) {
            $stats['kills'] = $this->killmails->count(['attackers.' . $type => $id, 'kill_time' => ['$gte' => new UTCDateTime((time() - ($timeRangeInDays * 86400)) * 1000)]]);
        } else {
            $stats['kills'] = $this->killmails->count(['attackers.' . $type => $id]);
        }

        if ($timeRangeInDays > 0) {
            $stats['losses'] = $this->killmails->count(['victim.' . $type => $id, 'kill_time' => ['$gte' => new UTCDateTime((time() - ($timeRangeInDays * 86400)) * 1000)]]);
        } else {
            $stats['losses'] = $this->killmails->count(['victim.' . $type => $id]);
        }

        // Track blob data
        $blobKills = 0;

        // Query for kills
        $attackerKillmails = $this->killmails->collection->find(
            $timeRangeInDays > 0 ? ['attackers.' . $type => $id, 'kill_time' => ['$gte' => new UTCDateTime((time() - ($timeRangeInDays * 86400)) * 1000)]] : ['attackers.' . $type => $id],
            ['projection' => [
                'killmail_id' => 1,
                'total_value' => 1,
                'is_solo' => 1,
                'kill_time' => 1,
                'attackers.' . $type => 1,
                'attackers.ship_id' => 1,
                'attackers.ship_name' => 1,
                'attackers.damage_done' => 1,
                'victim.damage_taken' => 1,
                'attackers.corporation_id' => 1,
                'attackers.corporation_name' => 1,
                'attackers.alliance_id' => 1,
                'attackers.alliance_name' => 1,
            ]
        ]);

        foreach ($attackerKillmails as $killmail) {
            $killmailId = $killmail['killmail_id'];
            $stats['iskKilled'] += $killmail['total_value'];
            $stats['soloKills'] += $killmail['is_solo'] ? 1 : 0;

            // Update lastActive with the latest kill_time
            $killTime = $killmail['kill_time'];
            if ($killTime instanceof UTCDateTime) {
                $killTime = $killTime->toDateTime(); // Convert MongoDB UTCDateTime to PHP DateTime
            }

            $killTimeUnix = $killTime->getTimestamp(); // Convert to Unix timestamp

            // Compare Unix timestamps and store the latest
            if ($killTimeUnix > $stats['lastActive']) {
                $stats['lastActive'] = $killTimeUnix;
            }

            // Heat map - Track kills by hour of the day (use string keys "00", "01", ..., "23")
            $hour = 'h' . $killTime->format('H'); // Ensure the hour is always a string
            $stats['heatMap'][$hour]++;

            // Calculate blob factor - count how many killmails have more than 10 attackers
            if (count($killmail['attackers']) > 10) {
                $blobKills++;
            }

            // Track if they did less than 1% damage (whore kill)
            foreach ($killmail['attackers'] as $attacker) {
                if ($attacker[$type] == $id) {
                    $damageDone = $attacker['damage_done'];
                    $totalDamage = $killmail['victim']['damage_taken'];
                    if ($damageDone < ($totalDamage * 0.01)) {
                        $stats['whoreKills']++;
                    }
                }
            }

            // Get most used ships and track same ship usage
            $entityShipTypeId = null;
            foreach ($killmail['attackers'] as $attacker) {
                if ($attacker[$type] == $id) {
                    // Set the entity's ship type
                    $entityShipTypeId = $attacker['ship_id'];
                    $shipName = $attacker['ship_name'] ?? 'Unknown';
                    if (!isset($stats['mostUsedShips'][$entityShipTypeId])) {
                        $stats['mostUsedShips'][$entityShipTypeId] = ['count' => 1, 'name' => $shipName];
                    } else {
                        $stats['mostUsedShips'][$entityShipTypeId]['count']++;
                    }
                } else {
                    // Track how often they flew the same ship as the other attackers
                    if ($entityShipTypeId !== null && $attacker['ship_id'] === $entityShipTypeId) {
                        $stats['sameShipAsOtherAttackers'][$killmailId] = true;
                    }
                }

                // Track corporations and alliances they flew with, using killmail_id as unique
                if ($attacker['corporation_id'] != $id && $attacker['corporation_id'] > 0) {
                    $corpId = $attacker['corporation_id'];
                    $corpName = $attacker['corporation_name'];
                    if (!isset($stats['fliesWithCorporations'][$corpId])) {
                        $stats['fliesWithCorporations'][$corpId] = ['count' => 1, 'name' => $corpName, 'killmails' => [$killmailId]];
                    } else {
                        if (!in_array($killmailId, $stats['fliesWithCorporations'][$corpId]['killmails'])) {
                            $stats['fliesWithCorporations'][$corpId]['count']++;
                            $stats['fliesWithCorporations'][$corpId]['killmails'][] = $killmailId;
                        }
                    }
                }

                if ($attacker['alliance_id'] != $id && $attacker['alliance_id'] > 0) {
                    $alliId = $attacker['alliance_id'];
                    $alliName = $attacker['alliance_name'];
                    if (!isset($stats['fliesWithAlliances'][$alliId])) {
                        $stats['fliesWithAlliances'][$alliId] = ['count' => 1, 'name' => $alliName, 'killmails' => [$killmailId]];
                    } else {
                        if (!in_array($killmailId, $stats['fliesWithAlliances'][$alliId]['killmails'])) {
                            $stats['fliesWithAlliances'][$alliId]['count']++;
                            $stats['fliesWithAlliances'][$alliId]['killmails'][] = $killmailId;
                        }
                    }
                }
            }

            // Sort mostUsedShips by count
            uasort($stats['mostUsedShips'], function ($a, $b) {
                return $b['count'] - $a['count'];
            });

            // Remove the ship with id 0 because it's #system which is broken
            unset($stats['mostUsedShips'][0]);
            // Remove all but top 10 most used ships
            $stats['mostUsedShips'] = array_slice($stats['mostUsedShips'], 0, 10, true);
        }

        // Calculate the blob factor percentage
        if ($stats['kills'] > 0) {
            $stats['blobFactor'] = ($blobKills / $stats['kills']) * 100;
        }

        // Query for losses
        $victimKillmails = $this->killmails->collection->find(
            $timeRangeInDays > 0 ? ['victim.' . $type => $id, 'kill_time' => ['$gte' => new UTCDateTime((time() - ($timeRangeInDays * 86400)) * 1000)]] : ['victim.' . $type => $id],
            ['projection' => [
                'total_value' => 1,
                'is_npc' => 1,
                'is_solo' => 1,
                'kill_time' => 1,
                'items' => 1,
                'victim.ship_id' => 1,
                'victim.ship_name' => 1,
                'attackers.corporation_id' => 1,
                'attackers.corporation_name' => 1,
                'attackers.alliance_id' => 1,
                'attackers.alliance_name' => 1,
            ]
        ]);

        foreach ($victimKillmails as $lossmail) {
            $stats['iskLost'] += $lossmail['total_value'];
            $stats['npcLosses'] += $lossmail['is_npc'] ? 1 : 0;
            $stats['soloLosses'] += $lossmail['is_solo'] ? 1 : 0;

            if ($type === 'character_id') {
                if ($lossmail['victim']['ship_id'] === 45534) {
                    $stats['possibleFC'] = true;
                }

                $killTime = $lossmail['kill_time']->toDateTime()->getTimestamp();
                $unixTime = strtotime('2019-09-01');
                if ($stats['kills'] < 25 && $killTime > $unixTime) {
                    foreach($lossmail['items'] as $item) {
                        if (in_array($item['type_id'], [28646, 21096, 52694])) {
                            $stats['possibleCynoAlt'] = true;
                            break;
                        }
                    }
                }
            }

            // Update lastActive with the latest kill_time
            $killTime = $lossmail['kill_time'];
            if ($killTime instanceof UTCDateTime) {
                $killTime = $killTime->toDateTime(); // Convert MongoDB UTCDateTime to PHP DateTime
            }

            $killTimeUnix = $killTime->getTimestamp();

            // Compare Unix timestamps and store the latest
            if ($killTimeUnix > $stats['lastActive']) {
                $stats['lastActive'] = $killTimeUnix;
            }

            // Get most lost ships
            $shipTypeId = $lossmail['victim']['ship_id'];
            $shipName = $lossmail['victim']['ship_name'] ?? 'Unknown';
            if (!isset($stats['mostLostShips'][$shipTypeId])) {
                $stats['mostLostShips'][$shipTypeId] = ['count' => 1, 'name' => $shipName];
            } else {
                $stats['mostLostShips'][$shipTypeId]['count']++;
            }

            // Sort mostLostShips by count
            uasort($stats['mostLostShips'], function ($a, $b) {
                return $b['count'] - $a['count'];
            });

            // Remove all but top 10 most lost ships
            $stats['mostLostShips'] = array_slice($stats['mostLostShips'], 0, 10, true);

            // Track most often died to corporations/alliances
            foreach ($lossmail['attackers'] as $attacker) {
                if ($attacker['corporation_id'] > 0) {
                    $corpId = $attacker['corporation_id'];
                    $corpName = $attacker['corporation_name'];
                    if (!isset($stats['diesToCorporations'][$corpId])) {
                        $stats['diesToCorporations'][$corpId] = ['count' => 1, 'name' => $corpName];
                    } else {
                        $stats['diesToCorporations'][$corpId]['count']++;
                    }
                }
                if ($attacker['alliance_id'] > 0) {
                    $alliId = $attacker['alliance_id'];
                    $alliName = $attacker['alliance_name'];
                    if (!isset($stats['diesToAlliances'][$alliId])) {
                        $stats['diesToAlliances'][$alliId] = ['count' => 1, 'name' => $alliName];
                    } else {
                        $stats['diesToAlliances'][$alliId]['count']++;
                    }
                }
            }

            // Sort diesToCorporations by count
            uasort($stats['diesToCorporations'], function ($a, $b) {
                return $b['count'] - $a['count'];
            });

            // Remove all but top 10 most often died to corporations
            $stats['diesToCorporations'] = array_slice($stats['diesToCorporations'], 0, 10, true);

            // Sort diesToAlliances by count
            uasort($stats['diesToAlliances'], function ($a, $b) {
                return $b['count'] - $a['count'];
            });

            // Remove all but top 10 most often died to alliances
            $stats['diesToAlliances'] = array_slice($stats['diesToAlliances'], 0, 10, true);
        }

        // Convert lastActive from Unix timestamp to formatted string, if not zero
        if ($stats['lastActive'] > 0) {
            $stats['lastActive'] = date('Y-m-d H:i:s', $stats['lastActive']);
        } else {
            $stats['lastActive'] = null;
        }

        // Count the number of unique killmails where they flew the same ship as other attackers
        $stats['sameShipAsOtherAttackers'] = count($stats['sameShipAsOtherAttackers']);

        // Sort fliesWithCorporations and fliesWithAlliances by their count, and limit to top 10
        uasort($stats['fliesWithCorporations'], function ($a, $b) {
            return $b['count'] - $a['count'];
        });
        $stats['fliesWithCorporations'] = array_slice($stats['fliesWithCorporations'], 0, 10, true);

        uasort($stats['fliesWithAlliances'], function ($a, $b) {
            return $b['count'] - $a['count'];
        });
        $stats['fliesWithAlliances'] = array_slice($stats['fliesWithAlliances'], 0, 10, true);

        // Clean up - remove 'killmails' key from corporation/alliance data
        foreach ($stats['fliesWithCorporations'] as $corpId => $corpData) {
            unset($stats['fliesWithCorporations'][$corpId]['killmails']);
        }
        foreach ($stats['fliesWithAlliances'] as $alliId => $alliData) {
            unset($stats['fliesWithAlliances'][$alliId]['killmails']);
        }

        return $stats;
    }
}
