<?php

namespace EK\Models;

use EK\Database\Collection;

class Celestials extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'celestials';

    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'ccp';

    /** @var string Primary index key */
    public string $indexField = 'item_id';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = ['item_id'];

    public array $indexes = [
        'unique' => ['item_id'],
        'desc' => ['type_id', 'solar_system_id', 'solar_system_name', 'region_id', 'region_name', [
            'x', 'y', 'z'
        ]]
    ];
}
