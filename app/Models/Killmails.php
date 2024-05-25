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
    public string $indexField = 'killmail_id';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = [];

    /** @var string[] $indexes The fields that should be indexed */
    public array $indexes = [
        'unique' => [
            ['killmail_id', 'hash']
        ],
        'desc' => [
            'kill_time', 'solar_system_id', 'solar_system_security', 'region_id', 'victim.character_id', 'victim.corporation_id',
            'victim.alliance_id', 'victim.faction_id', 'victim.ship_id', 'victim.ship_group_id', 'victim.damage_taken',
            'attackers.character_id', 'attackers.corporation_id', 'attackers.alliance_id', 'attackers.faction_id',
            'attackers.ship_id', 'attackers.ship_group_id', 'attackers.final_blow', 'attackers.weapon_id',
            'attackers.damageDone', 'items.type_id', 'items.group_id',
            [ 'attackers.character_id', 'kill_time' ],
            [ 'attackers.corporation_id', 'kill_time' ],
            [ 'attackers.alliance_id', 'kill_time' ],
            [ 'attackers.ship_id', 'kill_time' ],
            [ 'attackers.weapon_type_id', 'kill_time' ],
            [ 'total_value', 'kill_time' ]
        ]
    ];

    public function getRandom(): \Illuminate\Support\Collection
    {
        // Use the aggregate function with the $sample operator
        $result = $this->aggregate([
            ['$sample' => ['size' => 1]]
        ]);

        // The result is an array of documents, get the first one
        $document = $result->first();

        return collect($document);
    }
}
