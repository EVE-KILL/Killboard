<?php

declare(strict_types=1);

namespace EK\Helpers;

use EK\Models\Alliances;
use EK\Models\Characters;
use EK\Models\Corporations;
use EK\Models\InvFlags;
use EK\Models\Factions;
use EK\Models\GroupIDs;
use EK\Models\Killmails as KillmailModel;
use EK\Models\KillmailsESI;
use EK\Models\Prices;
use EK\Models\TypeIDs;
use EK\Models\UniverseCelestials;
use EK\Models\UniverseSystems;
use Exception;
use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;
use RuntimeException;

class Killmails
{
    protected string $imageServerUrl = 'https://images.evetech.net';

    public function __construct(
        protected KillmailModel $killmails,
        protected KillmailsESI $killmailsESI,
        protected UniverseSystems $universeSystems,
        protected UniverseCelestials $celestials,
        protected Prices $prices,
        protected TypeIDs $typeIDs,
        protected InvFlags $invFlags,
        protected Characters $characters,
        protected Corporations $corporations,
        protected Alliances $alliances,
        protected Factions $factions,
        protected GroupIDs $groupIDs,
    ) {

    }

    public function getKillMailHash(int $killId): string
    {
        return (string) $this->killmails->findOne(['killID' => $killId])->get('hash');
    }

    /**
     * @throws Exception
     */
    public function getKillmail(int $killId, string $hash = '', bool $debug = false): Collection
    {
        // Check if we got the killmail in the $this->killmailesi
        $killmail = $this->killmailsESI->findOne(['killmail_id' => $killId]);
        if ($killmail->isNotEmpty()) {
            return collect($killmail);
        }

        // Get killmail from ESI
        dd("fix fetching killmails from ESI");
        $killmail = $this->esiKillmails->getKillmail($killId, $hash);

        // Save to the database
        $this->killmailsESI->setData($killmail->toArray());
        $this->killmailsESI->save();

        return $killmail;
    }

    public function parseKillmail(int $killId, string $hash = '', int $warId = 0, bool $debug = false): Collection
    {
        try {
            $killmailData = $this->getKillmail($killId, $hash, $debug);
            $killmail = $this->generateInfoTop($killmailData, $killId, $hash, $warId, $debug);
            $killmail['victim'] = $this->generateVictim(collect($killmailData['victim']), $debug);
            $pointValue = $killmail['pointValue'];
            $totalDamage = $killmail['victim']['damageTaken'];
            $killmail['attackers'] = $this->generateAttackers(collect($killmailData['attackers']), $pointValue, $totalDamage, $debug);
            $killmail['items'] = $this->generateItems(collect($killmailData['victim']['items']), $killmailData['killmail_time'], $debug);
            $killmail['updated'] = new UTCDateTime(time() * 1000);

            return $killmail;
        } catch (Exception $e) {
            throw new RuntimeException('Error parsing killmail: ' . $e->getMessage(), 666, $e);
        }
    }

    private function generateInfoTop(Collection $killmail, int $killId, string $hash, int $warId = 0, bool $debug = false): Collection
    {
        $killTime = strtotime($killmail->get('killmail_time')) * 1000;
        $solarSystemData = $this->universeSystems->findOne(['solarSystemID' => $killmail->get('solar_system_id')]);
        $killValues = $this->calculateKillValue($killmail);
        $pointValue = ceil($killValues['totalValue'] === 0 ? 0 : ($killValues['totalValue'] / 10000) / count($killmail->get('attackers')));
        $x = $killmail['victim']['position']['x'] ?? 0;
        $y = $killmail['victim']['position']['y'] ?? 0;
        $z = $killmail['victim']['position']['z'] ?? 0;
        $shipTypeID = $killmail['victim->ship_type_id'] ?? 0;

        return collect([
            'killID' => (int) $killId,
            'hash' => (string) $hash,
            'killTime' => new UTCDateTime($killTime),
            'killTime_str' => $killmail['killmail_time'],
            'solarSystemID' => $solarSystemData->get('solarSystemID'),
            'solarSystemName' => $solarSystemData->get('solarSystemName'),
            'solarSystemSecurity' => $solarSystemData->get('security'),
            'regionID' => $solarSystemData->get('regionID'),
            'regionName' => $solarSystemData->get('regionName'),
            'near' => $this->getNear($x, $y, $z, $solarSystemData->get('solarSystemID')),
            'x' => $x,
            'y' => $y,
            'z' => $z,
            'shipValue' => (float) $killValues['shipValue'],
            'fittingValue' => (float) $killValues['itemValue'],
            'totalValue' => (float) $killValues['totalValue'],
            'pointValue' => $pointValue,
            'dna' => $this->getDNA($killmail['victim']['items'], $shipTypeID),
            'isNPC' => $this->isNPC($killmail),
            'isSolo' => $this->isSolo($killmail),
            'warID' => $warId,
        ]);
    }

    /**
     * @throws Exception
     */
    private function generateVictim(Collection $killmail, bool $debug = false): Collection
    {
        $characterID = $killmail['character_id'] ?? 0;
        $corporationID = $killmail['corporation_id'] ?? 0;
        $allianceID = $killmail['alliance_id'] ?? 0;
        $factionID = $killmail['faction_id'] ?? 0;
        $shipTypeID = $killmail['ship_type_id'] ?? 0;

        $characterInfo = $characterID > 0 ? $this->characters->getById($characterID) : new Collection();
        $corporationInfo = $corporationID > 0 ? $this->corporations->getById($corporationID) : new Collection();
        $allianceInfo = $allianceID > 0 ? $this->alliances->getById($allianceID) : new Collection();
        $factionInfo = $factionID > 0 ? $this->factions->findOne(['corporationID' => $factionID]) : new Collection();

        $shipInfo = $this->typeIDs->getAllByTypeID($shipTypeID);
        $groupData = $this->groupIDs->getAllByGroupID($shipInfo->get('groupID'));

        $shipTypeName = $shipTypeID > 0 ? $shipInfo->get('name') : '';
        $shipGroupName = $shipInfo->get('groupID') > 0 ? $groupData->get('name') : '';
        $victim = [
            'shipTypeID' => $shipTypeID > 0 ? $shipInfo->get('typeID') : 0,
            'shipTypeName' => $shipTypeName,
            'shipImageURL' => $this->imageServerUrl . "/types/{$shipTypeID}/render",
            'shipGroupID' => $shipInfo->get('groupID'),
            'shipGroupName' => $shipGroupName,
            'damageTaken' => $killmail['damage_taken'],
            'characterID' => $characterID,
            'characterName' => $characterID > 0 ? $characterInfo->get('characterName') : '',
            'characterImageURL' => $this->imageServerUrl . '/characters/' . $characterID . '/portrait',
            'corporationID' => $corporationID,
            'corporationName' => $corporationID > 0 ? $corporationInfo->get('corporationName') : '',
            'corporationImageURL' => $this->imageServerUrl . '/corporations/' . $corporationID . '/logo',
            'allianceID' => $allianceID,
            'allianceName' => $allianceID > 0 ? $allianceInfo->get('allianceName') : '',
            'allianceImageURL' => $this->imageServerUrl . '/alliances/' . $allianceID . '/logo',
            'factionID' => $factionID,
            'factionName' => $factionID > 0 ? $factionInfo->get('factionName') : '',
            'factionImageURL' => $this->imageServerUrl . '/alliances/' . $factionID . '/logo',
        ];

        if ($debug === false) {
            $this->characters->update(['characterID' => $characterID], ['$inc' => ['losses' => 1]]);
            $this->corporations->update(['corporationID' => $corporationID], ['$inc' => ['losses' => 1]]);
            if ($allianceID > 0) {
                $this->alliances->update(['allianceID' => $allianceID], ['$inc' => ['losses' => 1]]);
            }
        }

        return collect($victim);
    }

    private function generateAttackers(Collection $attackers, float $pointValue, float $totalDamage = 0, bool $debug = false): Collection
    {
        $return = [];

        foreach ($attackers as $attacker) {
            try {
                $characterID = $attacker['character_id'] ?? 0;
                $corporationID = $attacker['corporation_id'] ?? 0;
                $allianceID = $attacker['alliance_id'] ?? 0;
                $factionID = $attacker['faction_id'] ?? 0;
                $weaponTypeID = $attacker['weapon_type_id'] ?? 0;
                $shipTypeID = $attacker['ship_type_id'] ?? 0;
                $characterInfo = $characterID > 0 ? $this->characters->getById($characterID) : new Collection();
                $corporationInfo = $corporationID > 0 ? $this->corporations->getById($corporationID) : new Collection();
                $allianceInfo = $allianceID > 0 ? $this->alliances->getById($allianceID) : new Collection();
                $factionInfo = $factionID > 0 ? $this->factions->findOne(['corporationID' => $factionID]) : new Collection();

                $weaponTypeData = $weaponTypeID > 0 ? $this->typeIDs->getAllByTypeID($weaponTypeID) : '';
                $shipData = $this->typeIDs->getAllByTypeID($shipTypeID) ?? collect(['groupID' => 0]);
                $groupData = $shipData->get('groupID') > 0 ? $this->groupIDs->getAllByGroupID($shipData->get('groupID')) : collect([]);

                $shipTypeName = $shipTypeID > 0 ?  $shipData->get('name') : '';
                $shipGroupName = $shipData->get('groupID') > 0 ? $groupData->get('name') : '';
                $weaponTypeName = $weaponTypeID > 0 ? $weaponTypeData->get('name') : '';
                $inner = [
                    'shipTypeID' => $shipTypeID > 0 ? $shipData->get('typeID') : 0,
                    'shipTypeName' => $shipTypeName,
                    'shipImageURL' => $this->imageServerUrl . "/types/{$shipTypeID}/render",
                    'shipGroupID' => $shipData->get('groupID'),
                    'shipGroupName' => $shipGroupName,
                    'characterID' => $characterID,
                    'characterName' => $characterID > 0 ? $characterInfo->get('characterName') : '',
                    'characterImageURL' => $this->imageServerUrl . '/characters/' . $characterID . '/portrait',
                    'corporationID' => $corporationID,
                    'corporationName' => $corporationID > 0 ? $corporationInfo->get('corporationName') : '',
                    'corporationImageURL' => $this->imageServerUrl . '/corporations/' . $corporationID . '/logo',
                    'allianceID' => $allianceID,
                    'allianceName' => $allianceID > 0 ? $allianceInfo->get('allianceName') : '',
                    'allianceImageURL' => $this->imageServerUrl . '/alliances/' . $allianceID . '/logo',
                    'factionID' => $factionID,
                    'factionName' => $factionID > 0 ? $factionInfo->get('factionName') : '',
                    'factionImageURL' => $this->imageServerUrl . '/alliances/' . $factionID . '/logo',
                    'securityStatus' => $attacker['security_status'],
                    'damageDone' => $attacker['damage_done'],
                    'finalBlow' => $attacker['final_blow'],
                    'weaponTypeID' => $weaponTypeID,
                    'weaponTypeName' => $weaponTypeName,
                ];
                if ($attacker['damage_done'] === 0 || $totalDamage === 0) {
                    $inner['points'] = 0;
                } else {
                    $percentDamage = (int) $attacker['damage_done'] / $totalDamage;
                    $points = ceil($pointValue * $percentDamage);
                    if ($points > 0) {
                        $inner['points'] = $points;
                        if ($characterID > 0 && $debug === false) {
                            $this->characters->update(
                                ['characterID' => $characterID],
                                ['$inc' => ['points' => $inner['points']]]
                            );
                        }
                        if ($corporationID > 0 && $debug === false) {
                            $this->corporations->update(
                                ['corporationID' => $corporationID],
                                ['$inc' => ['points' => $inner['points']]]
                            );
                        }
                        if ($allianceID > 0 && $debug === false) {
                            $this->alliances->update(
                                ['allianceID' => $allianceID],
                                ['$inc' => ['points' => $inner['points']]]
                            );
                        }
                    }
                }
                if ($characterID > 0 && $debug === false) {
                    $this->characters->update(['characterID' => $characterID], ['$inc' => ['kills' => 1]]);
                }
                if ($corporationID > 0 && $debug === false) {
                    $this->corporations->update(['corporationID' => $corporationID], ['$inc' => ['kills' => 1]]);
                }
                if ($allianceID > 0 && $debug === false) {
                    $this->alliances->update(['allianceID' => $allianceID], ['$inc' => ['kills' => 1]]);
                }
                $return[] = $inner;
            } catch (Exception $e) {
                throw new RuntimeException($e->getMessage());
            }
        }

        return collect($return);
    }

    private function generateItems(Collection $items, string $killmailTime, bool $debug = false): Collection
    {
        $itemCollection = [];

        foreach ($items as $item) {
            try {
                $itemData = $this->typeIDs->getAllByTypeID($item['item_type_id']);
                $groupData = new Collection();
                if ($itemData->has('groupID')) {
                    $groupData = $this->groupIDs->getAllByGroupID((int) $itemData->get('groupID'));
                }
                $qtyDropped = $item['quantity_dropped'] ?? 0;
                $qtyDestroyed = $item['quantity_destroyed'] ?? 0;
                $typeName = $itemData->has('name') ? $itemData->get('name') : '';
                $groupName = $groupData->has('name') ? $groupData->get('name') : '';
                $dataForItemCollection = [
                    'typeID' => $item['item_type_id'],
                    'typeName' => $typeName,
                    'typeImageURL' => $this->imageServerUrl . '/types/' . $item['item_type_id'] . '/icon',
                    'groupID' => $itemData->get('groupID'),
                    'groupName' => $groupName,
                    'categoryID' => $groupData->get('categoryID'),
                    'flag' => $item['flag'],
                    'qtyDropped' => $qtyDropped,
                    'qtyDestroyed' => $qtyDestroyed,
                    'singleton' => $item['singleton'],
                    'value' => $this->prices->getPriceByTypeId($item['item_type_id'], date('Y-m-d', strtotime($killmailTime)))
                ];

                // If it's a container, it has items set inside of it
                if (isset($item['items'])) {
                    $dataForItemCollection['containerItems'] = $this->generateItems(collect($item['items']), $killmailTime, $debug)->toArray();
                }

                $itemCollection[] = $dataForItemCollection;
            } catch (Exception $e) {
                throw new RuntimeException($e->getMessage());
            }
        }

        return collect($itemCollection);
    }

    private function calculateKillValue(Collection $killmail): array
    {
        if ($killmail->isEmpty()) {
            return ['itemValue' => 0, 'shipValue' => 0, 'totalValue' => 0];
        }

        $shipTypeId = $killmail['victim']['ship_type_id'] ?? 0;
        $victimShipValue = $this->prices->getPriceByTypeId($shipTypeId, date('Y-m-d', strtotime($killmail['killmail_time'])));
        $killValue = 0;

        foreach($killmail['victim']['items'] as $item) {
            // If the $item contains it's own items, it's a container
            if (isset($item['items'])) {
                foreach($item['items'] as $cargoItem) {
                    $killValue += $this->getItemValue($cargoItem, $killmail['killmail_time'], true);
                }
            }

            $killValue += $this->getItemValue($item, $killmail['killmail_time']);
        }

        return ['itemValue' => $killValue, 'shipValue' => $victimShipValue, 'totalValue' => $killValue + $victimShipValue];
    }

    private function getItemValue(array $item, string $killTime, bool $isCargo = false): float
    {
        $typeId = $item['item_type_id'] ?? $item['type_id'];
        $flag = $item['flag'];
        $id = $this->typeIDs->getAllByTypeID($typeId);

        $itemName = null;

        if ($id->has('name')) {
            $itemName = $id->get('name');
        }

        if (!$itemName) {
            $itemName = 'TypeID ' . $typeId;
        }

        // Golden Pod
        if ($typeId === 33329 && $flag === 89) {
            $price = 0.01;
        } else {
            $price = $this->prices->getPriceByTypeId($typeId, date('Y-m-d', strtotime($killTime)));
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

        $celestials = $this->celestials->find(['solarSystemID' => $solarSystemId])->toArray();
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
            if (isset($celestial['typeName']) && str_contains($celestial['typeName'], $type)) {
                $string = $type;
                $string .= ' (';
                $string .= $celestial['itemName'] ?? $celestial['solarSystemName'];
                $string .= ')';
                $celestialName = $string;
            }
        }

        return $celestialName;
    }

    private function getDNA(array $items, $shipTypeID): string
    {
        $slots = [
            'LoSlot0','LoSlot1','LoSlot2','LoSlot3','LoSlot4','LoSlot5','LoSlot6','LoSlot7','MedSlot0',
            'MedSlot1','MedSlot2','MedSlot3','MedSlot4','MedSlot5','MedSlot6','MedSlot7','HiSlot0','HiSlot1','HiSlot2',
            'HiSlot3','HiSlot4','HiSlot5','HiSlot6','HiSlot7','DroneBay','RigSlot0','RigSlot1','RigSlot2','RigSlot3',
            'RigSlot4','RigSlot5','RigSlot6','RigSlot7','SubSystem0','SubSystem1','SubSystem2','SubSystem3',
            'SubSystem4','SubSystem5','SubSystem6','SubSystem7','SpecializedFuelBay',
        ];

        $fittingArray = [];
        $fittingString = $shipTypeID . ':';

        foreach ($items as $item) {
            $flagName = $this->invFlags->findOne(['flagID' => $item['flag']])->get('flagName');
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

    private function isNPC(Collection $killmail): bool
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

    private function isSolo(Collection $killmail): bool
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
