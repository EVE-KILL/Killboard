<?php

declare(strict_types=1);

namespace EK\Helpers;

use EK\Models\Killmails as KillmailsModel;
use EK\Models\KillmailsESI as KillmailsESIModel;
use EK\ESI\Killmails as ESIKillmails;
use EK\Models\SolarSystems as SolarSystemsModel;
use EK\ESI\SolarSystems as ESISolarSystems;
use EK\Models\Regions as RegionsModel;
use EK\ESI\Regions as ESIRegions;
use EK\Models\Constellations as ConstellationsModel;
use EK\ESI\Constellations as ESIConstellations;
use EK\Models\Prices as PricesModel;
use EK\Models\TypeIDs as TypeIDsModel;
use EK\ESI\TypeIDs as ESITypeIDs;
use EK\Models\GroupIDs as GroupIDsModel;
use EK\ESI\GroupIDs as ESIGroupIDs;
use EK\Models\Celestials as CelestialsModel;
use EK\Models\InvFlags as InvFlagsModel;
use EK\Models\Characters as CharactersModel;
use EK\Models\Corporations as CorporationsModel;
use EK\Models\Alliances as AlliancesModel;
use EK\Models\Factions as FactionsModel;
use EK\ESI\Characters as ESICharacters;
use EK\ESI\Corporations as ESICorporations;
use EK\ESI\Alliances as ESIAlliances;
use MongoDB\BSON\UTCDateTime;
use RuntimeException;

class Killmails
{
    protected string $imageServerUrl = 'https://images.evetech.net';

    public function __construct(
        protected KillmailsModel $killmails,
        protected KillmailsESIModel $killmailsESI,
        protected ESIKillmails $esiKillmails,
        protected SolarSystemsModel $solarSystems,
        protected ESISolarSystems $esiSolarSystems,
        protected RegionsModel $regions,
        protected ESIRegions $esiRegions,
        protected ConstellationsModel $constellations,
        protected ESIConstellations $esiConstellations,
        protected PricesModel $prices,
        protected TypeIDsModel $typeIDs,
        protected ESITypeIDs $esiTypeIDs,
        protected GroupIDsModel $groupIDs,
        protected ESIGroupIDs $esiGroupIDs,
        protected CelestialsModel $celestials,
        protected InvFlagsModel $invFlags,
        protected CharactersModel $characters,
        protected CorporationsModel $corporations,
        protected AlliancesModel $alliances,
        protected FactionsModel $factions,
        protected ESICharacters $esiCharacters,
        protected ESICorporations $esiCorporations,
        protected ESIAlliances $esiAlliances,
    ) {}

    public function getKillMailHash(int $killmail_id): string
    {
        $killmail = $this->killmails->findOneOrNull(['killmail_id' => $killmail_id]);
        return $killmail !== null ? (string)$killmail['hash'] : '';
    }

    public function getKillmail(int $killmail_id, string $hash = ''): array
    {
        // Check if we got the killmail
        $killmail = $this->killmailsESI->findOneOrNull(['killmail_id' => $killmail_id]);
        if ($killmail !== null) {
            return $killmail;
        }

        // Get killmail from ESI
        $killmail = $this->esiKillmails->getKillmail($killmail_id, $hash);

        // Save to the database
        $this->killmailsESI->setData($killmail);
        $this->killmailsESI->save();

        // Return the data from the model, because the model does stuff to it
        return $this->killmailsESI->getData();
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
        $solarSystemData = $this->solarSystems->findOneOrNull(['system_id' => $killmail['solar_system_id']]);
        if ($solarSystemData === null) {
            $solarSystemData = $this->esiSolarSystems->getSolarSystem($killmail['solar_system_id']);
        }
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
            'dna' => $this->generateDNA($killmail['victim']['items'], $shipTypeID),
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

            foreach ($entityIds as $id) {
                $result = $this->fetchEntityInformation($entityType, $id) ?? [];
                $information[$entityType][$id] = $result;
            }
        }

        return $information;
    }

    private function fetchEntityInformation(string $entityType, int $id): array|null
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
        $groupData = $this->groupIDs->findOneOrNull(['group_id' => $shipData['group_id']]) ??
            $this->esiGroupIDs->getGroupInfo($shipData['group_id']);

        $shipTypeId = $killmail['ship_type_id'] ?? 0;
        $characterId = $killmail['character_id'] ?? 0;
        $characterName = $information['character'][$characterId]['name'] ?? '';
        $corporationId = $killmail['corporation_id'] ?? 0;
        $corporationName = $information['corporation'][$corporationId]['name'] ?? '';
        $allianceId = $killmail['alliance_id'] ?? 0;
        $allianceName = $information['alliance'][$allianceId]['name'] ?? '';
        $factionId = $killmail['faction_id'] ?? 0;
        $factionName = $information['faction'][$factionId]['name'] ?? '';

        $victim = [
            'ship_id' => $shipTypeId,
            'ship_name' => $shipData['name'] ?? '',
            'ship_image_url' => $this->imageServerUrl . "/types/{$shipTypeId}/render",
            'ship_group_id' => $shipData['group_id'] ?? 0,
            'ship_group_name' => $groupData['name'] ?? '',
            'damage_taken' => $killmail['damage_taken'],
            'character_id' => $characterId,
            'character_name' => $characterName,
            'character_image_url' => $this->imageServerUrl . '/characters/' . $characterId . '/portrait',
            'corporation_id' => $corporationId,
            'corporation_name' => $corporationName,
            'corporation_image_url' => $this->imageServerUrl . '/corporations/' . $corporationId . '/logo',
            'alliance_id' => $allianceId,
            'alliance_name' => $allianceName,
            'alliance_image_url' => $this->imageServerUrl . '/alliances/' . $allianceId . '/logo',
            'faction_id' => $factionId,
            'faction_name' => $factionName,
            'faction_image_url' => $this->imageServerUrl . '/corporations/' . $factionId . '/logo',
        ];

        $this->characters->update(['character_id' => $characterId], ['$inc' => ['losses' => 1]]);
        $this->corporations->update(['corporation_id' => $corporationId], ['$inc' => ['losses' => 1]]);
        if ($allianceId > 0) {
            $this->alliances->update(['alliance_id' => $allianceId], ['$inc' => ['losses' => 1]]);
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
            $groupData = $this->groupIDs->findOneOrNull(['group_id' => $shipData['group_id']]) ??
                $this->esiGroupIDs->getGroupInfo($shipData['group_id']);

            $shipTypeName = $shipData['name'] ?? '';
            $shipGroupName = $groupData['name'] ?? '';
            $weaponTypeName = $weaponTypeData['name'] ?? '';
            $factionId = $attacker['faction_id'] ?? 0;
            $factionName = $information['faction'][$factionId]['name'] ?? '';
            $characterId = $attacker['character_id'] ?? 0;
            $characterName = $information['character'][$characterId]['name'] ?? '';
            $corporationId = $attacker['corporation_id'] ?? 0;
            $corporationName = $information['corporation'][$corporationId]['name'] ?? '';
            $allianceId = $attacker['alliance_id'] ?? 0;
            $allianceName = $information['alliance'][$allianceId]['name'] ?? '';

            $inner = [
                'ship_id' => $shipTypeID,
                'ship_name' => $shipTypeName,
                'ship_image_url' => $this->imageServerUrl . "/types/{$shipTypeID}/render",
                'ship_group_id' => $shipData['group_id'] ?? 0,
                'ship_group_name' => $shipGroupName,
                'character_id' => $characterId,
                'character_name' => $characterName,
                'character_image_url' => $this->imageServerUrl . '/characters/' . $characterId . '/portrait',
                'corporation_id' => $corporationId,
                'corporation_name' => $corporationName,
                'corporation_image_url' => $this->imageServerUrl . '/corporations/' . $corporationId . '/logo',
                'alliance_id' => $allianceId,
                'alliance_name' => $allianceName,
                'alliance_image_url' => $this->imageServerUrl . '/alliances/' . $allianceId . '/logo',
                'faction_id' => $factionId,
                'faction_name' => $factionName,
                'faction_image_url' => $this->imageServerUrl . '/corporations/' . $factionId . '/logo',
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
                    $inner['points'] = (int) $points;
                }
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
                $dataForItemCollection['container_items'] = $this->generateItems($item['items'], $killmailTime);
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

        // Limit the distance to 1000 AU in meters
        $distance = 1000 * 3.086e16;

        $celestials = $this->celestials->aggregate([
            ['$match' => [
                'solar_system_id' => $solarSystemId,
                'x' => ['$gt' => $x - $distance, '$lt' => $x + $distance],
                'y' => ['$gt' => $y - $distance, '$lt' => $y + $distance],
                'z' => ['$gt' => $z - $distance, '$lt' => $z + $distance],
            ]],
            ['$project' => [
                'item_id' => 1,
                'item_name' => 1,
                'constellation_id' => 1,
                'solar_system_id' => 1,
                'solar_system_name' => 1,
                'region_id' => 1,
                'region_name' => 1,
                'distance' => [
                    '$sqrt' => [
                        '$add' => [
                            ['$pow' => [['$subtract' => ['$x', $x]], 2]],
                            ['$pow' => [['$subtract' => ['$y', $y]], 2]],
                            ['$pow' => [['$subtract' => ['$z', $z]], 2]],
                        ]
                    ]
                ]
            ]],
            ['$sort' => ['distance' => 1]],
            ['$limit' => 1],
        ]);

        $celestial = iterator_to_array($celestials);
        if (empty($celestial)) {
            return '';
        }

        return $celestial[0]['item_name'] ?? '';
    }

    public function generateDNA(array $items, $shipTypeID): string
    {
        $slots = [
            'LoSlot0',
            'LoSlot1',
            'LoSlot2',
            'LoSlot3',
            'LoSlot4',
            'LoSlot5',
            'LoSlot6',
            'LoSlot7',
            'MedSlot0',
            'MedSlot1',
            'MedSlot2',
            'MedSlot3',
            'MedSlot4',
            'MedSlot5',
            'MedSlot6',
            'MedSlot7',
            'HiSlot0',
            'HiSlot1',
            'HiSlot2',
            'HiSlot3',
            'HiSlot4',
            'HiSlot5',
            'HiSlot6',
            'HiSlot7',
            'DroneBay',
            'RigSlot0',
            'RigSlot1',
            'RigSlot2',
            'RigSlot3',
            'RigSlot4',
            'RigSlot5',
            'RigSlot6',
            'RigSlot7',
            'SubSystem0',
            'SubSystem1',
            'SubSystem2',
            'SubSystem3',
            'SubSystem4',
            'SubSystem5',
            'SubSystem6',
            'SubSystem7',
            'SpecializedFuelBay',
        ];

        $fittingArray = [];
        $fittingString = $shipTypeID . ':';

        foreach ($items as $item) {
            $flagName = $this->invFlags->findOne(['flag_id' => $item['flag']])['flag_name'] ?? '';
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
