<?php

namespace EK\Database;

use EK\Cache\Cache;
use Exception;
use Illuminate\Support\Collection as IlluminateCollection;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\DeleteResult;
use MongoDB\GridFS\Bucket;
use MongoDB\UpdateResult;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;

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

    private function fixTimestamps(array|IlluminateCollection $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['$date'])) {
                $data[$key] = new UTCDateTime($value['$date']['$numberLong']);
            }
        }

        return $data instanceof IlluminateCollection ? $data->toArray() : $data;
    }

    public function find(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): IlluminateCollection
    {
        $span = $this->startSpan('db.query', 'find', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'find',
            'db.statement' => json_encode(compact('filter', 'options')),
        ]);

        $cacheKey = $this->generateCacheKey($filter, $options, $showHidden, get_class($this));
        $cacheKeyExists = $cacheTime > 0 && $this->cache->exists($cacheKey);

        if ($cacheTime > 0 && $cacheKeyExists) {
            $result = $this->cache->get($cacheKey);
            if ($result !== null) {
                $result = collect($result);
            } else {
                $result = $this->collection->find($filter, $options)->toArray();
            }
        } else {
            $result = $this->collection->find($filter, $options)->toArray();
        }

        $result = $this->fixTimestamps($result);

        if ($cacheTime > 0 && !$cacheKeyExists && !empty($result)) {
            $this->cache->set($cacheKey, $result, $cacheTime);
        }

        $span->finish();

        if ($showHidden) {
            return collect($result);
        }

        return (collect($result))->forget($this->hiddenFields);
    }

    public function findOne(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): IlluminateCollection
    {
        $span = $this->startSpan('db.query', 'findOne', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'findOne',
            'db.statement' => json_encode(compact('filter', 'options')),
        ]);

        $cacheKey = $this->generateCacheKey($filter, $options, $showHidden, get_class($this));
        $cacheKeyExists = $cacheTime > 0 && $this->cache->exists($cacheKey);

        if ($cacheTime > 0 && $cacheKeyExists) {
            $result = $this->cache->get($cacheKey);
            if ($result !== null) {
                $result = collect($result);
            } else {
                $result = $this->collection->findOne($filter, $options) ?? [];
            }
        } else {
            $result = $this->collection->findOne($filter, $options) ?? [];
        }

        $result = $this->fixTimestamps($result);

        if ($cacheTime > 0 && !$cacheKeyExists && !empty($result)) {
            $this->cache->set($cacheKey, $result, $cacheTime);
        }

        $span->finish();

        if ($showHidden) {
            return collect($result);
        }

        return (collect($result))->forget($this->hiddenFields);
    }

    public function findOneOrNull(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): ?IlluminateCollection
    {
        $span = $this->startSpan('db.query', 'findOneOrNull', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'findOne',
            'db.statement' => json_encode(compact('filter', 'options')),
        ]);

        $cacheKey = $this->generateCacheKey($filter, $options, $showHidden, get_class($this));
        $cacheKeyExists = $cacheTime > 0 && $this->cache->exists($cacheKey);

        if ($cacheTime > 0 && $cacheKeyExists) {
            $result = $this->cache->get($cacheKey);
            if ($result !== null) {
                $result = collect($result);
            } else {
                $result = $this->collection->findOne($filter, $options) ?? [];
            }
        } else {
            $result = $this->collection->findOne($filter, $options) ?? [];
        }

        $result = $this->fixTimestamps($result);

        if ($cacheTime > 0 && !$cacheKeyExists && !empty($result)) {
            $this->cache->set($cacheKey, $result, $cacheTime);
        }

        $span->finish();

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
        $span = $this->startSpan('db.query', 'aggregate', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'aggregate',
            'db.statement' => json_encode(compact('pipeline', 'options')),
        ]);

        $cacheKey = $this->generateCacheKey($pipeline, $options, get_class($this));
        $cacheKeyExists = $cacheTime > 0 && $this->cache->exists($cacheKey);

        if ($cacheTime > 0 && $cacheKeyExists) {
            $result = $this->cache->get($cacheKey);
            if ($result !== null) {
                $result = $result;
            } else {
                $result = $this->collection->aggregate($pipeline, $options)->toArray();
            }
        } else {
            $result = $this->collection->aggregate($pipeline, $options)->toArray();
        }

        $result = $this->fixTimestamps($result);

        if ($cacheTime > 0 && !$cacheKeyExists && !empty($result)) {
            $this->cache->set($cacheKey, $result, $cacheTime);
        }

        $span->finish();

        return collect($result);
    }

    public function count(array $filter = [], array $options = []): int
    {
        $span = $this->startSpan('db.query', 'count', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'countDocuments',
            'db.statement' => json_encode(compact('filter', 'options')),
        ]);

        $count = $this->collection->countDocuments($filter, $options);

        $span->finish();

        return $count;
    }

    public function aproximateCount(array $filter = [], array $options = []): int
    {
        $span = $this->startSpan('db.query', 'approximateCount', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'estimatedDocumentCount',
            'db.statement' => json_encode(compact('filter', 'options')),
        ]);

        $count = $this->collection->estimatedDocumentCount($filter, $options);

        $span->finish();

        return $count;
    }

    public function delete(array $filter = []): DeleteResult
    {
        if (empty($filter)) {
            throw new \Exception('Filter cannot be empty');
        }

        $span = $this->startSpan('db.query', 'delete', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'deleteOne',
            'db.statement' => json_encode(compact('filter')),
        ]);

        $result = $this->collection->deleteOne($filter);

        $span->finish();

        return $result;
    }

    public function update(array $filter = [], array $update = [], array $options = []): UpdateResult
    {
        if (empty($filter)) {
            throw new \Exception('Filter cannot be empty');
        }

        $span = $this->startSpan('db.query', 'update', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'updateOne',
            'db.statement' => json_encode(compact('filter', 'update', 'options')),
        ]);

        $result = $this->collection->updateOne($filter, $update, $options);

        $span->finish();

        return $result;
    }

    public function truncate(): void
    {
        $span = $this->startSpan('db.query', 'truncate', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'drop',
        ]);

        try {
            $this->collection->drop();
        } catch (\Exception $e) {
            SentrySdk::getCurrentHub()->captureException($e);
            throw new \Exception('Error truncating collection: ' . $e->getMessage());
        }

        $span->finish();
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
        $span = $this->startSpan('db.query', 'saveMany', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'bulkWrite',
        ]);

        $bulkWrites = [];

        foreach ($this->data->all() as $document) {
            $this->hasRequired($document instanceof IlluminateCollection ? $document->all() : $document);

            if (empty($this->indexField)) {
                throw new Exception('Error: indexField is empty. Cannot save data in class ' . get_class($this) . ' without an indexField.');
            }

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

            unset($document['last_modified']);
            unset($document['_id']);

            $bulkWrites[] = ['updateOne' => [
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

        $span->finish();

        return $result->getUpsertedCount() + $result->getInsertedCount() + $result->getModifiedCount();
    }

    public function save(): int
    {
        $span = $this->startSpan('db.query', 'save', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'updateOne',
        ]);

        $document = $this->data->all();
        $this->hasRequired($document);

        if (empty($this->indexField)) {
            throw new Exception('Error: indexField is empty. Cannot save data in class ' . get_class($this) . ' without an indexField.');
        }

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

        $span->finish();

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
        $span = $this->startSpan('db.query', 'ensureIndexes', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'createIndexes',
        ]);

        $existingIndexes = $this->listIndexes();
        $indexNames = [];

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

        foreach ($existingIndexes as $index) {
            if (!in_array($index['name'], $indexNames)) {
                $this->dropIndex([$index['name']]);
            }
        }

        foreach ($this->indexes as $indexType => $indexes) {
            if ($indexType === 'text' && count($indexes) > 1) {
                throw new Exception('Error: There can only be one text index in a collection, refer to https://www.mongodb.com/docs/manual/core/indexes/index-types/index-text/');
            }

            foreach($indexes as $index) {
                if (is_array($index)) {
                    $name = implode('_', $index);

                    $direction = match($indexType) {
                        'desc', 'unique' => -1,
                        'asc' => 1,
                        'text' => 'text',
                        default => null
                    };

                    if ($direction === 1) {
                        $name .= '_1';
                    }

                    $options = match($indexType) {
                        'unique' => ['unique' => true],
                        default => ['sparse' => true]
                    };

                    $options['name'] = $name;
                    $options['background'] = true;

                    $modifiedIndex = [];
                    foreach ($index as $key) {
                        $modifiedIndex[$key] = $direction;
                    }

                    $this->createIndex($modifiedIndex, $options);
                } else {
                    $name = $index . (($indexType === 'text') ? '_text' : '');

                    $direction = match($indexType) {
                        'desc', 'unique' => -1,
                        'asc' => 1,
                        'text' => 'text',
                        default => null
                    };

                    $options = match($indexType) {
                        'unique' => ['unique' => true],
                        default => ['sparse' => true]
                    };

                    $options['name'] = $name;

                    $this->createIndex([$index => $direction], $options);
                }
            }
        }

        $span->finish();
    }

    public function createIndex(array $keys = [], array $options = []): void
    {
        $span = $this->startSpan('db.query', 'createIndex', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'createIndex',
            'db.statement' => json_encode(compact('keys', 'options')),
        ]);

        $this->collection->createIndex($keys, $options);

        $span->finish();
    }

    public function dropIndex(array $keys = [], array $options = []): void
    {
        $span = $this->startSpan('db.query', 'dropIndex', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'dropIndex',
            'db.statement' => json_encode(compact('keys', 'options')),
        ]);

        foreach ($keys as $key) {
            $this->collection->dropIndex($key, $options);
        }

        $span->finish();
    }

    public function dropIndexes(): void
    {
        $span = $this->startSpan('db.query', 'dropIndexes', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'dropIndexes',
        ]);

        $this->collection->dropIndexes();

        $span->finish();
    }

    public function listIndexes(): array
    {
        $span = $this->startSpan('db.query', 'listIndexes', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'listIndexes',
        ]);

        $indexes = $this->collection->listIndexes();

        $filteredIndexes = collect($indexes)->filter(function ($index) {
            return $index['name'] !== '_id_';
        })->toArray();

        $span->finish();

        return $filteredIndexes;
    }

    public function generateCacheKey(...$args): string
    {
        return md5(serialize($args));
    }

    protected function startSpan(string $operation, string $description, array $data = []): \Sentry\Tracing\Span
    {
        $hub = SentrySdk::getCurrentHub();
        $span = $hub->getSpan();

        if ($span === null) {
            // No active span, start a new transaction
            $transactionContext = new TransactionContext();
            $transactionContext->setName('db');
            $transactionContext->setOp('db');
            $transactionContext->setDescription($description);
            $transaction = $hub->startTransaction($transactionContext);
            $hub->setSpan($transaction);

            $span = $transaction->startChild(new SpanContext());
        } else {
            $span = $span->startChild(new SpanContext());
        }

        $span->setOp($operation);
        $span->setData($data);

        return $span;
    }
}
