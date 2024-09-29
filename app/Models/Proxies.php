<?php

namespace EK\Models;

use EK\Database\Collection;

class Proxies extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'proxies';

    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'app';

    /** @var string Primary index key */
    public string $indexField = 'proxy_id';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = [];

    /** @var string[] $indexes The fields that should be indexed */
    public array $indexes = [
        'unique' => ['proxy_id'],
        'desc' => ['status', 'last_validated']
    ];

    public function getRandomProxy(): array
    {
        // Select a random proxy from the collection where status is 'active'
        $proxy = $this->find(['status' => 'active'], cacheTime: 300);
        $proxy = iterator_to_array($proxy);

        return $proxy[array_rand($proxy)];
    }
}
