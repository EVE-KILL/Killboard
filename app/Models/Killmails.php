<?php

namespace EK\Models;

use EK\Database\Collection;

class Killmails extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'killmails';

    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'app';

    /** @var string Primary index key */
    public string $indexField = 'killID';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = [];

    /** @var string[] $indexes The fields that should be indexed */
    public array $indexes = [
        'unique' => [
            ['killID', 'hash']
        ],
        'desc' => [
            'killTime', 'solarSystemID', 'solarSystemSecurity', 'regionID', 'victim.characterID', 'victim.corporationID',
            'victim.allianceID', 'victim.factionID', 'victim.shipTypeID', 'victim.shipGroupID', 'victim.damageTaken',
            'attackers.characterID', 'attackers.corporationID', 'attackers.allianceID', 'attackers.factionID',
            'attackers.shipTypeID', 'attackers.shipGroupID', 'attackers.finalBlow', 'attackers.weaponTypeID',
            'attackers.damageDone', 'items.typeID', 'items.groupID', 'updated',
            [ 'attackers.characterID', 'killTime' ],
            [ 'attackers.corporationID', 'killTime' ],
            [ 'attackers.allianceID', 'killTime' ],
            [ 'attackers.shipTypeID', 'killTime' ],
            [ 'attackers.weaponTypeID', 'killTime' ],
            [ 'totalValue', 'killTime' ]
        ],
        'asc' => [
            'killTime',
            [ 'totalValue', 'killTime' ]
        ],
        'text' => []
    ];
}
