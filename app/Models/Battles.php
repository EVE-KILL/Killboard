<?php

namespace EK\Models;

use EK\Database\Collection;

class Battles extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'battles';

    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'app';

    /** @var string Primary index key */
    public string $indexField = 'battle_id';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = ['battle_id'];

    /** @var string[] $indexes The fields that should be indexed */
    public array $indexes = [
        'unique' => ['battle_id'],
        'desc' => ['start_time', 'end_time']
    ];
}
