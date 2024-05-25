<?php

namespace EK\Models;

use EK\Database\Collection;

class Factions extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'factions';

    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'ccp';

    /** @var string Primary index key */
    public string $indexField = 'faction_id';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = ['faction_id'];

    public array $indexes = [
        'unique' => ['faction_id'],
        'desc' => ['corporation_id', 'solar_system_id', 'militia_corporation_id'],
        'text' => ['name']
    ];
}
