<?php

namespace EK\Models;

use EK\Database\Collection;

class Wars extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'wars';

    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'app';

    /** @var string Primary index key */
    public string $indexField = 'id';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = ['id'];

    public array $indexes = [
        'unique' => ['id'],
        'desc' => [
            'aggressor.alliance_id',
            'defender.alliance_id',
            'aggressor.corporation_id',
            'defender.corporation_id',
            'started',
            'finished',
            'retracted',
            'declared'
        ],
    ];
}
