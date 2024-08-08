<?php

namespace EK\Models;

use EK\Database\Collection;

class Alliances extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'alliances';

    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'app';

    /** @var string Primary index key */
    public string $indexField = 'alliance_id';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = ['alliance_id'];

    /** @var string[] $indexes The fields that should be indexed */
    public array $indexes = [
        'unique' => ['alliance_id'],
        'desc' => ['creator_corporation_id', 'executor_corporation_id', 'kills', 'losses', 'updated', 'name', 'last_updated'],
        'text' => ['name']
    ];
}
