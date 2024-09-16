<?php

namespace EK\Models;

use EK\Database\Collection;

class Stations extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'stations';

    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'ccp';

    /** @var string Primary index key */
    public string $indexField = 'station_id';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = ['station_id'];

    public array $indexes = [
        'unique' => ['station_id'],
        'desc' => ['type_id', 'system_id'],
        'text' => []
    ];
}
