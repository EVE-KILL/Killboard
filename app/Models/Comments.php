<?php

namespace EK\Models;

use EK\Database\Collection;

class Comments extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'comments';

    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'app';

    /** @var string Primary index key */
    public string $indexField = 'identifier';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = ['identifier', 'comment', 'character'];

    public array $indexes = [
        'unique' => [['identifier', 'comment', 'character.character_id']],
        'desc' => [
            'character.character_id',
            'character.character_name',
            'created_at_desc'
        ],
        'asc' => [
            'created_at_asc'
        ],
    ];
}
