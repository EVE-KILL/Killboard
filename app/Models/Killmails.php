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
            // General fields
            'kill_time',
            'system_id',
            'region_id',
            'system_security',
            'war_id',
            'last_modified',
            'near',
            'is_npc',
            'is_solo',
            'total_value',

            // Items
            'items.type_id',
            'items.group_id',

            // Victim fields
            'victim.ship_group_id',
            [ 'victim.character_id', 'kill_time' ],
            [ 'victim.corporation_id', 'kill_time' ],
            [ 'victim.alliance_id', 'kill_time' ],
            [ 'victim.faction_id', 'kill_time' ],
            [ 'victim.ship_id', 'kill_time' ],
            [ 'victim.weapon_type_id', 'kill_time' ],

            // Attacker fields
            'attackers.final_blow',
            'attackers.ship_group_id',
            [ 'attackers.character_id', 'kill_time' ],
            [ 'attackers.corporation_id', 'kill_time' ],
            [ 'attackers.alliance_id', 'kill_time' ],
            [ 'attackers.faction_id', 'kill_time' ],
            [ 'attackers.ship_id', 'kill_time' ],
            [ 'attackers.weapon_type_id', 'kill_time' ],
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
