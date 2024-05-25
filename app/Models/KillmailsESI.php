<?php

namespace EK\Models;

use EK\Database\Collection;
use MongoDB\BSON\UTCDateTime;

class KillmailsESI extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'killmails_esi';

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
        'unique' => [ 'killmail_id' ],
        'desc' => [ 'last_modified', 'killmail_time' ]
    ];

    public function setData(array $data = []): void
    {
        if (!isset($data['killmail_id'])) {
            throw new \Exception('Missing killmail_id');
        }

        $data['last_modified'] = new UTCDateTime();
        $data['killmail_time_str'] = $data['killmail_time'];
        $data['killmail_time'] = new UTCDateTime(strtotime($data['killmail_time']) * 1000);

        ksort($data);
        parent::setData($data);
    }
}
