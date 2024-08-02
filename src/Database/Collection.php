<?php

namespace EK\Database;

use EK\Cache\Cache;
use Exception;
use Illuminate\Support\Collection as IlluminateCollection;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\DeleteResult;
use MongoDB\Driver\BulkWrite;
use MongoDB\GridFS\Bucket;
use MongoDB\UpdateResult;

class Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = '';
    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'esi';
    /** @var \MongoDB\Collection MongoDB CollectionInterface */
    public \MongoDB\Collection $collection;
    /** @var Bucket MongoDB GridFS Bucket for storing files */
    public Bucket $bucket;
    /** @var string Primary index key */
    public string $indexField = '';
    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = [];
    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = [];
    /** @var string[] $indexes The fields that should be indexed */
    public array $indexes = [
        'unique' => [],
        'desc' => [],
        'asc' => [],
        'text' => []
    ];
    /** @var IlluminateCollection Data collection when storing data */
    protected IlluminateCollection $data;
    /** @var Client MongoDB client connection */
    private Client $client;

    public function __construct(
        protected Cache $cache,
        protected Connection $connection,
    ) {
        $this->client = $connection->getConnection();

        $this->collection = $this->client
            ->selectDatabase($this->databaseName)
            ->selectCollection($this->collectionName);

        $this->bucket = $this->client
            ->selectDatabase($this->databaseName)
            ->selectGridFSBucket();

        $this->data = new IlluminateCollection();
    }

    private function fixTimestamps(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['$date'])) {
                $data[$key] = new UTCDateTime($value['$date']['$numberLong']);
            }
        }

        return $data;
    }

    public function find(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): IlluminateCollection
    {
        $cacheKey = $this->generateCacheKey($filter, $options, $showHidden, get_class($this));
        $cacheKeyExists = $cacheTime > 0 && $this->cache->exists($cacheKey);

        $result = $cacheTime > 0 && $cacheKeyExists ?
            $this->cache->get($cacheKey) :
            $this->collection->find($filter, $options)->toArray();

        $result = $this->fixTimestamps($result);

        if ($cacheTime > 0 && !$cacheKeyExists && !empty($result)) {
            $this->cache->set($cacheKey, $result, $cacheTime);
        }

        if ($showHidden) {
            return collect($result);
        }

        return (collect($result))->forget($this->hiddenFields);
    }

    public function findOne(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): IlluminateCollection
    {
        $cacheKey = $this->generateCacheKey($filter, $options, $showHidden, get_class($this));
        $cacheKeyExists = $cacheTime > 0 && $this->cache->exists($cacheKey);

        if ($cacheKeyExists) {
            $result = $this->cache->get($cacheKey);
        }

        if (!$cacheKeyExists || $result === null) {
            $result = $this->collection->findOne($filter, $options) ?? [];
        }

        $result = $this->fixTimestamps($result);

        if ($cacheTime > 0 && !$cacheKeyExists && !empty($result)) {
            $this->cache->set($cacheKey, $result, $cacheTime);
        }

        if ($showHidden) {
            return collect($result);
        }

        return (collect($result))->forget($this->hiddenFields);
    }

    public function findOneOrNull(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): ?IlluminateCollection
    {
        $cacheKey = $this->generateCacheKey($filter, $options, $showHidden, get_class($this));
        $cacheKeyExists = $cacheTime > 0 && $this->cache->exists($cacheKey);

        if ($cacheKeyExists) {
            $result = $this->cache->get($cacheKey);
        }

        if (!$cacheKeyExists || $result === null) {
            $result = $this->collection->findOne($filter, $options) ?? [];
        }

        $result = $this->fixTimestamps($result);

        if ($cacheTime > 0 && !$cacheKeyExists && !empty($result)) {
            $this->cache->set($cacheKey, $result, $cacheTime);
        }

        if (empty($result)) {
            return null;
        }

        if ($showHidden) {
            return collect($result);
        }

        return (collect($result))->forget($this->hiddenFields);
    }

    public function aggregate(array $pipeline = [], array $options = [], int $cacheTime = 60): IlluminateCollection
    {
        $cacheKey = $this->generateCacheKey($pipeline, $options, get_class($this));
        $cacheKeyExists = $cacheTime > 0 && $this->cache->exists($cacheKey);

        if ($cacheKeyExists) {
            $result = $this->cache->get($cacheKey);
        }

        if (!$cacheKeyExists || $result === null) {
            $result = $this->collection->aggregate($pipeline, $options)->toArray();
        }

        $result = $this->fixTimestamps($result);

        if ($cacheTime > 0 && !$cacheKeyExists && !empty($result)) {
            $this->cache->set($cacheKey, $result, $cacheTime);
        }

        return collect($result);
    }

    public function count(array $filter = [], array $options = []): int
    {
        return $this->collection->countDocuments($filter, $options);
    }

    public function delete(array $filter = []): DeleteResult
    {
        if (empty($filter)) {
            throw new \Exception('Filter cannot be empty');
        }

        return $this->collection->deleteOne($filter);
    }

    public function update(array $filter = [], array $update = [], array $options = []): UpdateResult
    {
        if (empty($filter)) {
            throw new \Exception('Filter cannot be empty');
        }

        return $this->collection->updateOne($filter, $update, $options);
    }

    public function truncate(): void
    {
        try {
            $this->collection->drop();
        } catch (\Exception $e) {
            throw new \Exception('Error truncating collection: ' . $e->getMessage());
        }
    }

    public function setData(array $data = []): void
    {
        $this->data = collect($data);
    }

    public function getData(): IlluminateCollection
    {
        return $this->data;
    }

    public function saveMany(): int
    {
        $bulkWrite = new BulkWrite();
        $bulkWrites = [];

        foreach($this->data->all() as $document) {
            // Does it have the required fields?
            $this->hasRequired($document instanceof IlluminateCollection ? $document->all() : $document);

            // Do we have an indexField? Otherwise throw an exception
            if (empty($this->indexField)) {
                throw new Exception('Error: indexField is empty. Cannot save data in class ' . get_class($this) . ' without an indexField.');
            }

            // Create match array for unique fields
            $match = [];
            if (isset($this->indexes['unique'])) {
                foreach ($this->indexes['unique'] as $uniqueField) {
                    if (is_array($uniqueField)) {
                        foreach ($uniqueField as $field) {
                            if (isset($document[$field])) {
                                $match[$field] = $document[$field];
                            }
                        }
                    } else {
                        if (isset($document[$uniqueField])) {
                            $match[$uniqueField] = $document[$uniqueField];
                        }
                    }
                }
            } else {
                $match[$this->indexField] = $this->data->get($this->indexField);
            }

            // Ensure the document doesn't contain last_modified or _id
            unset($document['last_modified']);
            unset($document['_id']);

            $bulkWrites[] = ['updateOne' => [
                    // Use the unique index fields to match the document
                    $match,
                    [
                        '$set' => $document,
                        '$currentDate' => ['last_modified' => true],
                    ],
                    [
                        'upsert' => true
                    ]
                ]
            ];
        }

        $result = $this->collection->bulkWrite($bulkWrites);
        return $result->getUpsertedCount() + $result->getInsertedCount() + $result->getModifiedCount();
    }

    public function save(): int
    {
        $document = $this->data->all();
        // Does it have the required fields?
        $this->hasRequired($document);

        // Do we have an indexField? Otherwise throw an exception
        if (empty($this->indexField)) {
            throw new Exception('Error: indexField is empty. Cannot save data in class ' . get_class($this) . ' without an indexField.');
        }

        // Create match array for unique fields
        $match = [];
        if (isset($this->indexes['unique'])) {
            foreach ($this->indexes['unique'] as $uniqueField) {
                if (is_array($uniqueField)) {
                    foreach ($uniqueField as $field) {
                        if (isset($document[$field])) {
                            $match[$field] = $document[$field];
                        }
                    }
                } else {
                    if (isset($document[$uniqueField])) {
                        $match[$uniqueField] = $document[$uniqueField];
                    }
                }
            }
        } else {
            $match[$this->indexField] = $document[$this->indexField];
        }

        // Ensure the document doesn't contain last_modified or _id
        unset($document['last_modified']);
        unset($document['_id']);

        $result = $this->collection->updateOne(
            $match,
            [
                '$set' => $document,
                '$currentDate' => ['last_modified' => true],
            ],
            [
                'upsert' => true
            ]
        );

        return $result->getUpsertedCount() + $result->getModifiedCount();
    }

    public function hasRequired(array $data): void
    {
        foreach ($this->required as $requiredField) {
            if (!isset($data[$requiredField])) {
                throw new Exception('Error: Required field ' . $requiredField . ' is missing in data.');
            }
        }
    }

    public function ensureIndexes(): void
    {
        $existingIndexes = $this->listIndexes();
        $indexNames = [];

        // Add a last_modified index to all collections if it doesn't exist, as desc
        foreach($this->indexes as $indexType => $indexes) {
            if ($indexType === 'desc') {
                if (!in_array('last_modified', $indexes)) {
                    $this->indexes['desc'][] = 'last_modified';
                }
            }
        }

        foreach ($this->indexes as $indexType => $indexes) {
            foreach($indexes as $index) {
                if (is_array($index)) {
                    $name = implode('_', $index);
                } else {
                    $name = $index . (($indexType === 'text') ? '_text' : '');
                }

                $indexNames[] = $name;
            }
        }

        // Drop indexes that shouldn't exist
        foreach ($existingIndexes as $index) {
            if (!in_array($index['name'], $indexNames)) {
                $this->dropIndex([$index['name']]);
            }
        }

        // Create indexes that should exist
        foreach ($this->indexes as $indexType => $indexes) {
            if ($indexType === 'text' && count($indexes) > 1) {
                throw new Exception('Error: There can only be one text index in a collection, refer to https://www.mongodb.com/docs/manual/core/indexes/index-types/index-text/');
            }

            foreach($indexes as $index) {
                if (is_array($index)) {
                    $name = implode('_', $index);

                    // Set the direction of the index
                    $direction = match($indexType) {
                        'desc', 'unique' => -1,
                        'asc' => 1,
                        'text' => 'text',
                        default => null
                    };

                    // Set the options of the index
                    $options = match($indexType) {
                        'unique' => ['unique' => true],
                        default => ['sparse' => true]
                    };

                    // Add the name to the options
                    $options['name'] = $name;

                    // Create the index in the background
                    $options['background'] = true;

                    // Modify the index to add the direction
                    $modifiedIndex = [];
                    foreach ($index as $key) {
                        $modifiedIndex[$key] = $direction;
                    }

                    // Create the index
                    $this->createIndex($modifiedIndex, $options);
                } else {
                    // Give the index a name
                    $name = $index . (($indexType === 'text') ? '_text' : '');

                    // Set the direction of the index
                    $direction = match($indexType) {
                        'desc', 'unique' => -1,
                        'asc' => 1,
                        'text' => 'text',
                        default => null
                    };

                    // Set the options of the index
                    $options = match($indexType) {
                        'unique' => ['unique' => true],
                        default => ['sparse' => true]
                    };

                    // Add the name to the options
                    $options['name'] = $name;

                    // Create the index
                    $this->createIndex([$index => $direction], $options);
                }
            }
        }
    }

    public function createIndex(array $keys = [], array $options = []): void
    {
        $this->collection->createIndex($keys, $options);
    }

    public function dropIndex(array $keys = [], array $options = []): void
    {
        foreach ($keys as $key) {
            $this->collection->dropIndex($key, $options);
        }
    }

    public function dropIndexes(): void
    {
        $this->collection->dropIndexes();
    }

    public function listIndexes(): array
    {
        $indexes = $this->collection->listIndexes();

        // We need to filter out the _id_ index
        return collect($indexes)->filter(function ($index) {
            return $index['name'] !== '_id_';
        })->toArray();
    }

    public function generateCacheKey(...$args): string
    {
        return md5(serialize($args));
    }
}
