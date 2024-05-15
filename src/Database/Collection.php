<?php

namespace EK\Database;

use EK\Api\CollectionInterface;
use Exception;
use Illuminate\Support\Collection as IlluminateCollection;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\DeleteResult;
use MongoDB\GridFS\Bucket;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use Traversable;

class Collection implements CollectionInterface
{
    /** @var string Name of collection in database */
    public string $collectionName = '';
    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'esi';
    /** @var \MongoDB\Collection MongoDB Collection */
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

    public function find(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): IlluminateCollection
    {
        $result = $this->collection->find($filter, $options)->toArray();

        if ($showHidden) {
            return collect($result);
        }

        return (collect($result))->forget($this->hiddenFields);
    }

    public function findOne(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): IlluminateCollection
    {
        $result = $this->collection->findOne($filter, $options) ?? [];
        if ($showHidden) {
            return collect($result);
        }

        return (collect($result))->forget($this->hiddenFields);
    }

    public function aggregate(array $pipeline = [], array $options = []): Traversable
    {
        return $this->collection->aggregate($pipeline, $options);
    }

    public function count(array $filter = [], array $options = [], int $cacheTime = 60): int
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

    public function saveMany(): void
    {
        $this->collection->insertMany($this->data->all());
    }

    public function save(): UpdateResult|InsertOneResult
    {
        // Does it have the required fields?
        $this->hasRequired();

        // Do we have an indexField? Otherwise throw an exception
        if (empty($this->indexField)) {
            throw new Exception('Error: indexField is empty. Cannot save data in class ' . get_class($this) . ' without an indexField.');
        }

        try {
            return $this->collection->updateOne(
                [$this->indexField => $this->data->get($this->indexField)],
                [
                    '$set' => $this->data->all(),
                    '$currentDate' => ['lastModified' => true],
                ],
                [
                    'upsert' => true
                ]
            );
        } catch (Exception $e) {
            throw new Exception('Error occurred during transaction: ' . $e->getMessage());
        }
    }

    public function clear(array $data = []): self
    {
        $this->data = new IlluminateCollection();
        if (!empty($data)) {
            $this->data = $this->data->merge($data);
        }

        return $this;
    }

    public function makeTimeFromDateTime(string $dateTime): UTCDateTime
    {
        return new UTCDateTime(strtotime($dateTime) * 1000);
    }

    public function makeTimeFromUnixTime(int $unixTime): UTCDateTime
    {
        return new UTCDateTime($unixTime * 1000);
    }

    public function makeTime(string|int $time): UTCDateTime
    {
        if (is_int($time)) {
            return $this->makeTimeFromUnixTime($time);
        }

        return $this->makeTimeFromDateTime($time);
    }

    public function hasRequired(): bool
    {
        if (!empty($this->required)) {
            foreach ($this->required as $key) {
                if (!$this->data->has($key)) {
                    throw new Exception('Error: ' . $key . ' does not exist in data..' . PHP_EOL . print_r($this->data->all(), true));
                }
            }
        }

        return true;
    }

    public function ensureIndexes(): void
    {
        foreach ($this->indexes as $indexType => $indexes) {
            // If indexType is text, and there are multiple entries in the indexes array, we need to throw an exception, there can only be one text index
            if ($indexType === 'text' && count($indexes) > 1) {
                throw new Exception('Error: There can only be one text index in a collection, refer to https://www.mongodb.com/docs/manual/core/indexes/index-types/index-text/');
            }

            foreach ($indexes as $index) {
                $modifier = ($indexType === 'desc') ? -1 : (($indexType === 'asc') ? 1 : (($indexType === 'unique') ? -1 : ($indexType === 'text' ? 'text' : null)));
                if (is_array($index)) {
                    if ($modifier !== null) {
                        $modifiedIndex = [];
                        foreach ($index as $key) {
                            $modifiedIndex[$key] = $modifier;
                        }
                        $options = ($indexType === 'unique') ? ['unique' => true] : ['sparse' => true];
                        $this->createIndex($modifiedIndex, $options);
                    }
                } else {
                    if ($modifier !== null) {
                        $this->createIndex([$index => $modifier], ($indexType === 'unique') ? ['unique' => true] : ['sparse' => true]);
                    }
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
        return collect($this->collection->listIndexes())->toArray();
    }

}
