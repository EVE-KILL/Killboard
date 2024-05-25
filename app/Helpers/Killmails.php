<?php

declare(strict_types=1);

namespace EK\Helpers;

use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;
use RuntimeException;

class Killmails
{
    protected string $imageServerUrl = 'https://images.evetech.net';

    public function __construct(
        protected \EK\Models\Killmails      $killmails,
        protected \EK\Models\KillmailsESI   $killmailsESI,
        protected \EK\ESI\Killmails         $esiKillmails,
        protected \EK\Models\SolarSystems   $solarSystems,
        protected \EK\ESI\SolarSystems      $esiSolarSystems,
        protected \EK\Models\Regions        $regions,
        protected \EK\ESI\Regions           $esiRegions,
        protected \EK\Models\Constellations $constellations,
        protected \EK\ESI\Constellations    $esiConstellations,
        protected \EK\Models\Prices         $prices,
        protected \EK\Models\TypeIDs        $typeIDs,
        protected \EK\ESI\TypeIDs           $esiTypeIDs,
        protected \EK\Models\GroupIDs       $groupIDs,
        protected \EK\ESI\GroupIDs          $esiGroupIDs,
        protected \EK\Models\Celestials     $celestials,
        protected \EK\Models\InvFlags       $invFlags,
        protected \EK\Models\Characters     $characters,
        protected \EK\Models\Corporations   $corporations,
        protected \EK\Models\Alliances      $alliances,
        protected \EK\Models\Factions       $factions,
        protected \EK\ESI\Characters        $esiCharacters,
        protected \EK\ESI\Corporations      $esiCorporations,
        protected \EK\ESI\Alliances         $esiAlliances,
    )
    {
    }

    public function getKillMailHash(int $killmail_id): string
    {
        return (string)$this->killmails->findOne(['killmail_id' => $killmail_id])->get('hash');
    }

    public function getKillmail(int $killmail_id, string $hash = ''): array
    {
        // Check if we got the killmail
        $killmail = $this->killmailsESI->findOne(['killmail_id' => $killmail_id]);
        if ($killmail->isNotEmpty()) {
            return $killmail->toArray();
        }

        // Get killmail from ESI
        $killmail = $this->esiKillmails->getKillmail($killmail_id, $hash);

        // Save to the database
        $this->killmailsESI->setData($killmail);
        $this->killmailsESI->save();

        // Return the data from the model, because the model does stuff to it
        return $this->killmailsESI->getData()->toArray();
    }

    public function parseKillmail(int $killmail_id, string $hash = '', int $war_id = 0): array
    {
        $killmailData = $this->getKillmail($killmail_id, $hash);
        $killmail = $this->generateInfoTop($killmailData, $killmail_id, $hash, $war_id);
        $killmail['victim'] = $this->generateVictim($killmailData['victim']);
        $pointValue = $killmail['point_value'];
        $totalDamage = $killmail['victim']['damage_taken'];
        $killmail['attackers'] = $this->generateAttackers($killmailData['attackers'], $pointValue, $totalDamage);
        $killmail['items'] = $this->generateItems($killmailData['victim']['items'], $killmailData['killmail_time']);

        return $killmail;
    }

    private function generateInfoTop(array $killmail, int $killmail_id, string $hash, int $war_id = 0): array
    {
        $solarSystemData = $this->solarSystems->findOneOrNull(['system_id' => $killmail['solar_system_id']]) ??
            $this->esiSolarSystems->getSolarSystem($killmail['solar_system_id']);
        $killValue = $this->calculateKillValue($killmail);
        $pointValue = ceil($killValue['total_value'] === 0 ? 0 : ($killValue['total_value'] / 10000) / count($killmail['attackers']));
        $x = $killmail['victim']['position']['x'] ?? 0;
        $y = $killmail['victim']['position']['y'] ?? 0;
        $z = $killmail['victim']['position']['z'] ?? 0;
        $shipTypeID = $killmail['victim']['ship_type_id'] ?? 0;

        return [
            'killmail_id' => (int)$killmail_id,
            'hash' => (string)$hash,
            'kill_time' => $killmail['killmail_time'],
            'kill_time_str' => $killmail['killmail_time_str'],
            'system_id' => $solarSystemData['system_id'],
            'system_name' => $solarSystemData['name'],
            'system_security' => $solarSystemData['security_status'],
            'region_id' => $solarSystemData['region_id'],
            'region_name' => $solarSystemData['region_name'],
            'near' => $this->getNear($x, $y, $z, $solarSystemData['system_id']),
            'x' => $x,
            'y' => $y,
            'z' => $z,
            'ship_value' => (float)$killValue['ship_value'],
            'fitting_value' => (float)$killValue['item_value'],
            'total_value' => (float)$killValue['total_value'],
            'point_value' => $pointValue,
            'dna' => $this->getDNA($killmail['victim']['items'], $shipTypeID),
            'is_npc' => $this->isNPC($killmail),
            'is_solo' => $this->isSolo($killmail),
            'war_id' => $war_id,
        ];
    }

    private function getInformation(array $entitiesToFetch): array
    {
        $information = [];

        foreach ($entitiesToFetch as $entityType => $entityIds) {
            $entityIds = is_array($entityIds) ? $entityIds : [$entityIds];

            foreach($entityIds as $id) {
                $result = $this->fetchEntityInformation($entityType, $id) ?? [];
                $information[$entityType][$id] = is_a($result, Collection::class) ? $result->toArray() : $result;
            }
        }

        return $information;
    }

    private function fetchEntityInformation(string $entityType, int $id): Collection|array|null
    {
        return match ($entityType) {
            'character' => $this->characters->findOneOrNull(['character_id' => $id]) ?? $this->esiCharacters->getCharacterInfo($id),
            'corporation' => $this->corporations->findOneOrNull(['corporation_id' => $id]) ?? $this->esiCorporations->getCorporationInfo($id),
            'alliance' => $this->alliances->findOneOrNull(['alliance_id' => $id]) ?? $this->esiAlliances->getAllianceInfo($id),
            'faction' => $this->factions->findOneOrNull(['$or' => [['corporation_id' => $id], ['faction_id' => $id]]]),
            'solarSystem' => $this->solarSystems->findOneOrNull(['system_id' => $id]) ?? $this->esiSolarSystems->getSolarSystem($id),
            'region' => $this->regions->findOneOrNull(['region_id' => $id]) ?? $this->esiRegions->getRegion($id),
            'constellation' => $this->constellations->findOneOrNull(['constellation_id' => $id]) ?? $this->esiConstellations->getConstellation($id),
            'celestial' => $this->celestials->findOneOrNull(['item_id' => $id]),
            default => throw new RuntimeException('Invalid type provided'),
        };
    }

    private function generateVictim(array $killmail): array
    {
        $information = $this->getInformation([
            'character' => $killmail['character_id'] ?? 0,
            'corporation' => $killmail['corporation_id'] ?? 0,
            'alliance' => $killmail['alliance_id'] ?? 0,
            'faction' => $killmail['faction_id'] ?? 0,
        ]);

        $shipData = $this->typeIDs->findOneOrNull(['type_id' => $killmail['ship_type_id']]) ??
            $this->esiTypeIDs->getTypeInfo($killmail['ship_type_id']);
        $groupData = $this->groupIDs->findOneOrNull(['group_id' => $shipData->get('group_id')]) ??
            $this->esiGroupIDs->getGroupInfo($shipData->get('group_id'));

        $shipTypeId = $killmail['ship_type_id'] ?? 0;
        $factionId = $killmail['faction_id'] ?? 0;

        $victim = [
            'ship_id' => $shipTypeId,
            'ship_name' => $shipData['name'] ?? '',
            'ship_image_url' => $this->imageServerUrl . "/types/{$shipTypeId}/render",
            'ship_group_id' => $shipData['group_id'] ?? 0,
            'ship_group_name' => $groupData['name'] ?? '',
            'damage_taken' => $killmail['damage_taken'],
            'character_id' => $killmail['character_id'] ?? 0,
            'character_name' => $information['character'][$killmail['character_id']]['name'] ?? '',
            'character_image_url' => $this->imageServerUrl . '/characters/' . $killmail['character_id'] ?? 0 . '/portrait',
            'corporation_id' => $killmail['corporation_id'] ?? 0,
            'corporation_name' => $information['corporation'][$killmail['corporation_id']]['name'] ?? '',
            'corporation_image_url' => $this->imageServerUrl . '/corporations/' . $killmail['corporation_id'] ?? 0 . '/logo',
            'alliance_id' => $killmail['alliance_id'] ?? 0,
            'alliance_name' => $information['alliance'][$killmail['alliance_id']]['name'] ?? '',
            'alliance_image_url' => $this->imageServerUrl . '/alliances/' . $killmail['alliance_id'] ?? 0 . '/logo',
            'faction_id' => $factionId,
            'faction_name' => $information['faction'][$factionId]['name'] ?? '',
            'faction_image_url' => $this->imageServerUrl . '/corporations/' . $factionId . '/logo',
        ];

        $this->characters->update(['character_id' => $killmail['character_id']], ['$inc' => ['losses' => 1]]);
        $this->corporations->update(['corporation_id' => $killmail['corporation_id']], ['$inc' => ['losses' => 1]]);
        if ($killmail['alliance_id'] > 0) {
            $this->alliances->update(['alliance_id' => $killmail['alliance_id']], ['$inc' => ['losses' => 1]]);
        }

        return $victim;
    }

    private function generateAttackers(array $attackers, float $pointValue, float $totalDamage = 0): array
    {
        $return = [];

        foreach ($attackers as $attacker) {
            $information = $this->getInformation([
                'character' => $attacker['character_id'] ?? 0,
                'corporation' => $attacker['corporation_id'] ?? 0,
                'alliance' => $attacker['alliance_id'] ?? 0,
                'faction' => $attacker['faction_id'] ?? 0,
            ]);

            $weaponTypeID = $attacker['weapon_type_id'] ?? 0;
            $shipTypeID = $attacker['ship_type_id'] ?? 0;

            $weaponTypeData = $this->typeIDs->findOneOrNull(['type_id' => $weaponTypeID]) ??
                $this->esiTypeIDs->getTypeInfo($weaponTypeID);
            $shipData = $this->typeIDs->findOneOrNull(['type_id' => $shipTypeID]) ??
                $this->esiTypeIDs->getTypeInfo($shipTypeID);
            $groupData = $this->groupIDs->findOneOrNull(['group_id' => $shipData->get('group_id')]) ??
                $this->esiGroupIDs->getGroupInfo($shipData->get('group_id'));

            $shipTypeName = $shipData['name'] ?? '';
            $shipGroupName = $groupData['name'] ?? '';
            $weaponTypeName = $weaponTypeData['name'] ?? '';
            $factionId = $attacker['faction_id'] ?? 0;

            $inner = [
                'ship_id' => $shipTypeID,
                'ship_name' => $shipTypeName,
                'ship_image_url' => $this->imageServerUrl . "/types/{$shipTypeID}/render",
                'ship_group_id' => $shipData['group_id'] ?? 0,
                'ship_group_name' => $shipGroupName,
                'character_id' => $attacker['character_id'],
                'character_name' => $information['character'][$attacker['character_id']]['name'] ?? '',
                'character_image_url' => $this->imageServerUrl . '/characters/' . $attacker['character_id'] . '/portrait',
                'corporation_id' => $attacker['corporation_id'],
                'corporation_name' => $information['corporation'][$attacker['corporation_id']]['name'] ?? '',
                'corporation_image_url' => $this->imageServerUrl . '/corporations/' . $attacker['corporation_id'] . '/logo',
                'alliance_id' => $attacker['alliance_id'] ?? 0,
                'alliance_name' => $attacker['alliance_id'] > 0 ? $information['alliance'][$attacker['alliance_id']]['name'] : '',
                'alliance_image_url' => $this->imageServerUrl . '/alliances/' . $attacker['alliance_id'] ?? 0 . '/logo',
                'faction_id' => $factionId,
                'faction_name' => $factionId > 0 ? $information['faction'][$factionId]['name'] : '',
                'faction_image_url' => $this->imageServerUrl . '/alliances/' . $factionId . '/logo',
                'security_status' => $attacker['security_status'],
                'damage_done' => $attacker['damage_done'],
                'final_blow' => $attacker['final_blow'],
                'weapon_type_id' => $weaponTypeID,
                'weapon_type_name' => $weaponTypeName,
            ];

            if ($attacker['damage_done'] === 0 || $totalDamage === 0) {
                $inner['points'] = 0;
            } else {
                $percentDamage = (int)$attacker['damage_done'] / $totalDamage;
                $points = ceil($pointValue * $percentDamage);
                if ($points > 0) {
                    $inner['points'] = $points;
                    if ($attacker['character_id'] > 0) {
                        $this->characters->update(
                            ['character_id' => $attacker['character_id']],
                            ['$inc' => ['points' => $points]]
                        );
                    }
                    if ($attacker['corporation_id'] > 0) {
                        $this->corporations->update(
                            ['corporation_id' => $attacker['corporation_id']],
                            ['$inc' => ['points' => $points]]
                        );
                    }
                    if ($attacker['alliance_id'] > 0) {
                        $this->alliances->update(
                            ['alliance_id' => $attacker['alliance_id']],
                            ['$inc' => ['points' => $points]]
                        );
                    }
                }
            }
            if ($attacker['character_id'] > 0) {
                $this->characters->update(['character_id' => $attacker['character_id']], ['$inc' => ['kills' => 1]]);
            }
            if ($attacker['corporation_id'] > 0) {
                $this->corporations->update(['corporationID' => $attacker['corporation_id']], ['$inc' => ['kills' => 1]]);
            }
            if ($attacker['alliance_id'] > 0) {
                $this->alliances->update(['allianceID' => $attacker['alliance_id']], ['$inc' => ['kills' => 1]]);
            }

            $return[] = $inner;
        }

        return $return;
    }

    private function generateItems(array $items, UTCDateTime $killmailTime): array
    {
        $itemCollection = [];

        foreach ($items as $item) {
            $itemData = $this->typeIDs->findOneOrNull(['type_id' => $item['item_type_id']]) ??
                $this->esiTypeIDs->getTypeInfo($item['item_type_id']);

            $groupData = $this->groupIDs->findOneOrNull(['group_id' => $itemData['group_id']]) ??
                $this->esiGroupIDs->getGroupInfo($itemData['group_id']);

            $qtyDropped = $item['quantity_dropped'] ?? 0;
            $qtyDestroyed = $item['quantity_destroyed'] ?? 0;
            $typeName = $itemData['name'] ?? '';
            $groupName = $groupData['name'] ?? '';

            $dataForItemCollection = [
                'type_id' => $item['item_type_id'],
                'type_name' => $typeName,
                'type_image_url' => $this->imageServerUrl . '/types/' . $item['item_type_id'] . '/icon',
                'group_id' => $itemData['group_id'] ?? 0,
                'group_name' => $groupName,
                'category_id' => $groupData['category_id'] ?? 0,
                'flag' => $item['flag'],
                'qty_dropped' => $qtyDropped,
                'qty_destroyed' => $qtyDestroyed,
                'singleton' => $item['singleton'],
                'value' => $this->prices->getPriceByTypeId($item['item_type_id'], $killmailTime),
            ];

            // If it's a container, it has items set inside of it
            if (isset($item['items'])) {
                $dataForItemCollection['container_items'] = $this->generateItems($item['items'], $killmailTime)->toArray();
            }

            $itemCollection[] = $dataForItemCollection;
        }

        return $itemCollection;
    }

    private function calculateKillValue(array $killmail): array
    {
        $shipTypeId = $killmail['victim']['ship_type_id'] ?? 0;
        $victimShipValue = $this->prices->getPriceByTypeId($shipTypeId, $killmail['killmail_time']);
        $killValue = 0;

        foreach ($killmail['victim']['items'] as $item) {
            // If the $item contains it's own items, it's a container
            if (isset($item['items'])) {
                foreach ($item['items'] as $cargoItem) {
                    $killValue += $this->getItemValue($cargoItem, $killmail['killmail_time'], true);
                }
            }

            $killValue += $this->getItemValue($item, $killmail['killmail_time']);
        }

        return ['item_value' => $killValue, 'ship_value' => $victimShipValue, 'total_value' => $killValue + $victimShipValue];
    }

    private function getItemValue(array $item, UTCDateTime $killTime, bool $isCargo = false): float
    {
        $typeId = $item['item_type_id'] ?? $item['type_id'];
        $flag = $item['flag'];
        $id = $this->typeIDs->findOneOrNull(['type_id' => $typeId]) ?? $this->esiTypeIDs->getTypeInfo($typeId);
        $itemName = $id['name'] ?? 'Type ID ' . $typeId;

        // Golden Pod
        if ($typeId === 33329 && $flag === 89) {
            $price = 0.01;
        } else {
            $price = $this->prices->getPriceByTypeId($typeId, $killTime);
        }

        if ($isCargo && str_contains($itemName, 'Blueprint')) {
            $item['singleton'] = 2;
        }

        if ($item['singleton'] === 2) {
            $price /= 100;
        }

        $dropped = $item['quantity_dropped'] ?? 0;
        $destroyed = $item['quantity_destroyed'] ?? 0;

        return $price * ($dropped + $destroyed);
    }

    private function getNear($x, $y, $z, int $solarSystemId): string
    {
        if ($x === 0 && $y === 0 && $z === 0) {
            return '';
        }

        $celestials = $this->celestials->find(['solar_system_id' => $solarSystemId])->toArray();
        $minimumDistance = null;
        $celestialName = '';

        foreach ($celestials as $celestial) {
            $distance = sqrt((($celestial['x'] - $x) ** 2) + (($celestial['y'] - $y) ** 2) + (($celestial['z'] - $z) ** 2));

            if ($minimumDistance === null || $distance >= $minimumDistance) {
                $minimumDistance = $distance;
                $celestialName = $this->fillInCelestialName($celestial);
            }
        }

        return $celestialName;
    }

    private function fillInCelestialName(array $celestial): string
    {
        $celestialName = '';
        $types = ['Stargate', 'Moon', 'Planet', 'Asteroid Belt', 'Sun'];
        foreach ($types as $type) {
            if (isset($celestial['type_name']) && str_contains($celestial['type_name'], $type)) {
                $string = $type;
                $string .= ' (';
                $string .= $celestial['item_name'] ?? $celestial['solar_system_name'];
                $string .= ')';
                $celestialName = $string;
            }
        }

        return $celestialName;
    }

    private function getDNA(array $items, $shipTypeID): string
    {
        $slots = [
            'LoSlot0', 'LoSlot1', 'LoSlot2', 'LoSlot3', 'LoSlot4', 'LoSlot5', 'LoSlot6', 'LoSlot7', 'MedSlot0',
            'MedSlot1', 'MedSlot2', 'MedSlot3', 'MedSlot4', 'MedSlot5', 'MedSlot6', 'MedSlot7', 'HiSlot0', 'HiSlot1', 'HiSlot2',
            'HiSlot3', 'HiSlot4', 'HiSlot5', 'HiSlot6', 'HiSlot7', 'DroneBay', 'RigSlot0', 'RigSlot1', 'RigSlot2', 'RigSlot3',
            'RigSlot4', 'RigSlot5', 'RigSlot6', 'RigSlot7', 'SubSystem0', 'SubSystem1', 'SubSystem2', 'SubSystem3',
            'SubSystem4', 'SubSystem5', 'SubSystem6', 'SubSystem7', 'SpecializedFuelBay',
        ];

        $fittingArray = [];
        $fittingString = $shipTypeID . ':';

        foreach ($items as $item) {
            $flagName = $this->invFlags->findOne(['flag_id' => $item['flag']])->get('flag_name');
            $categoryID = $item['category_id'] ?? 0;
            if ($categoryID === 8 || in_array($flagName, $slots)) {
                $typeID = $item['item_type_id'] ?? 0;
                $dropped = $item['quantity_dropped'] ?? 0;
                $destroyed = $item['quantity_destroyed'] ?? 0;
                if (isset($fittingArray[$typeID])) {
                    $fittingArray[$typeID]['count'] += ($dropped + $destroyed);
                } else {
                    $fittingArray[$typeID] = ['count' => $dropped + $destroyed];
                }
            }
        }

        foreach ($fittingArray as $key => $item) {
            $fittingString .= "{$key};{$item['count']}:";
        }
        $fittingString .= ':';
        return $fittingString;
    }

    private function isNPC(array $killmail): bool
    {
        $npc = 0;
        $calc = 0;
        $kdCount = count($killmail['attackers']);

        foreach ($killmail['attackers'] as $attacker) {
            $characterID = $attacker['character_id'] ?? 0;
            $corporationID = $attacker['corporation_id'] ?? 0;
            $npc += $characterID === 0 && ($corporationID < 1999999 && $corporationID !== 1000125) ? 1 : 0;
        }

        if ($kdCount > 0 && $npc > 0) {
            $calc = count($killmail['attackers']) / $npc;
        }

        return $calc === 1;
    }

    private function isSolo(array $killmail): bool
    {
        $npc = 0;
        $calc = 0;
        $kdCount = count($killmail['attackers']);

        if ($kdCount > 2) {
            return false;
        } elseif ($kdCount === 1) {
            return true;
        }

        foreach ($killmail['attackers'] as $attacker) {
            $characterID = $attacker['character_id'] ?? 0;
            $corporationID = $attacker['corporation_id'] ?? 0;
            $npc += $characterID === 0 && ($corporationID < 1999999 && $corporationID !== 1000125) ? 1 : 0;
        }
        if ($npc > 0) {
            $calc = 2 / $npc;
        }

        return $calc === 2;
    }
}
