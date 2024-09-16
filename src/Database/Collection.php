<?php

namespace EK\Database;

use EK\Cache\Cache;
use Exception;
use Generator;
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
    /** @var array Data collection when storing data */
    protected array $data;
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

        $this->data = [];
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

    public function find(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): Generator
    {
        $span = $this->startSpan('db.query', 'find', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'find',
            'db.statement' => json_encode(compact('filter', 'options')),
        ]);

        $cacheKey = $this->generateCacheKey($filter, $options, $showHidden, get_class($this));
        $cacheKeyExists = $cacheTime > 0 && $this->cache->exists($cacheKey);

        if ($cacheTime > 0 && $cacheKeyExists) {
            $cachedResult = $this->cache->get($cacheKey);
            if ($cachedResult !== null) {
                yield from $this->yieldFixedTimestamps($cachedResult, $showHidden);
                $span->finish();
                return;
            }
        }

        $cursor = $this->collection->find($filter, $options);
        $resultCount = 0;
        $cachedResults = [];

        foreach ($cursor as $document) {
            $document = $this->fixTimestamps([$document])[0];
            $resultCount++;

            if ($cacheTime > 0) {
                $cachedResults[] = $document;
            }

            if (!$showHidden) {
                $document = $this->removeHiddenFields($document);
            }

            yield $document;
        }

        if ($cacheTime > 0 && !$cacheKeyExists && $resultCount > 0) {
            $this->cache->set($cacheKey, $cachedResults, $cacheTime);
        }

        $span->finish();
    }

    public function findOne(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): array
    {
        $options['limit'] = 1;
        $result = $this->find($filter, $options, $cacheTime, $showHidden)->current();
        return $result !== null ? $result : [];
    }

    public function findOneOrNull(array $filter = [], array $options = [], int $cacheTime = 60, bool $showHidden = false): ?array
    {
        $result = $this->findOne($filter, $options, $cacheTime, $showHidden);
        return !empty($result) ? $result : null;
    }

    public function aggregate(array $pipeline = [], array $options = [], int $cacheTime = 60): Generator
    {
        $span = $this->startSpan('db.query', 'aggregate', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'aggregate',
            'db.statement' => json_encode(compact('pipeline', 'options')),
        ]);

        $cacheKey = $this->generateCacheKey($pipeline, $options, get_class($this));
        $cacheKeyExists = $cacheTime > 0 && $this->cache->exists($cacheKey);

        if ($cacheTime > 0 && $cacheKeyExists) {
            $cachedResult = $this->cache->get($cacheKey);
            if ($cachedResult !== null) {
                yield from $this->yieldFixedTimestamps($cachedResult, true);
                $span->finish();
                return;
            }
        }

        $cursor = $this->collection->aggregate($pipeline, $options);
        $resultCount = 0;
        $cachedResults = [];

        foreach ($cursor as $document) {
            $document = $this->fixTimestamps([$document])[0];
            $resultCount++;

            if ($cacheTime > 0) {
                $cachedResults[] = $document;
            }

            yield $document;
        }

        if ($cacheTime > 0 && !$cacheKeyExists && $resultCount > 0) {
            $this->cache->set($cacheKey, $cachedResults, $cacheTime);
        }

        $span->finish();
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

    public function aproximateCount(array $options = []): int
    {
        $span = $this->startSpan('db.query', 'approximateCount', [
            'db.collection' => $this->collectionName,
            'db.operation' => 'estimatedDocumentCount',
            'db.statement' => json_encode(compact('options')),
        ]);

        $count = $this->collection->estimatedDocumentCount($options);

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
        $this->data = $data;
    }

    public function getData(): array
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

        foreach ($this->data as $document) {
            $this->hasRequired($document);

            if (empty($this->indexField)) {
                throw new Exception('Error: indexField is empty. Cannot save data in class ' . get_class($this) . ' without an indexField.');
            }

            $match = [];
            if (isset($this->indexes['unique'])) {
                foreach ((array) $this->indexes['unique'] as $uniqueField) {
                    $fields = (array)$uniqueField;
                    foreach ($fields as $field) {
                        if (isset($document[$field])) {
                            $match[$field] = $document[$field];
                        }
                    }
                }
            } else {
                $match[$this->indexField] = $document[$this->indexField];
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

        $document = $this->data;
        $this->hasRequired($document);

        if (empty($this->indexField)) {
            throw new Exception('Error: indexField is empty. Cannot save data in class ' . get_class($this) . ' without an indexField.');
        }

        $match = [];
        if (isset($this->indexes['unique'])) {
            foreach ((array) $this->indexes['unique'] as $uniqueField) {
                $fields = (array)$uniqueField;
                foreach ($fields as $field) {
                    if (isset($document[$field])) {
                        $match[$field] = $document[$field];
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

        foreach ($this->indexes as $indexType => $indexes) {
            if ($indexType === 'desc') {
                $descIndexes = (array)$indexes;
                if (!in_array('last_modified', $descIndexes)) {
                    $this->indexes['desc'][] = 'last_modified';
                }
            }
        }

        foreach ($this->indexes as $indexType => $indexes) {
            foreach ((array)$indexes as $index) {
                $indexArray = (array)$index;
                $name = implode('_', $indexArray);

                $indexNames[] = $name;
            }
        }

        foreach ($existingIndexes as $index) {
            if (!in_array($index['name'], $indexNames)) {
                $this->dropIndex([$index['name']]);
            }
        }

        foreach ($this->indexes as $indexType => $indexes) {
            if ($indexType === 'text' && count((array)$indexes) > 1) {
                throw new Exception('Error: There can only be one text index in a collection, refer to https://www.mongodb.com/docs/manual/core/indexes/index-types/index-text/');
            }

            foreach ((array)$indexes as $index) {
                $indexArray = (array)$index;
                $name = implode('_', $indexArray);

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
                foreach ($indexArray as $key) {
                    $modifiedIndex[$key] = $direction;
                }

                try {
                    $this->createIndex($modifiedIndex, $options);
                } catch (\Exception $e) {
                    SentrySdk::getCurrentHub()->captureException($e);
                    dump($e->getMessage());
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

        $indexes = iterator_to_array($this->collection->listIndexes());

        $filteredIndexes = array_filter($indexes, function ($index) {
            return $index['name'] !== '_id_';
        });

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

    private function yieldFixedTimestamps(array $data, bool $showHidden): Generator
    {
        foreach ($data as $item) {
            $item = $this->fixTimestamps([$item])[0];
            if (!$showHidden) {
                $item = $this->removeHiddenFields($item);
            }
            yield $item;
        }
    }

    private function removeHiddenFields(array $data): array
    {
        return array_diff_key($data, array_flip($this->hiddenFields));
    }
}
