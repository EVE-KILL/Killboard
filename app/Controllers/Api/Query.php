<?php

namespace EK\Controllers\Api;

use ArrayIterator;
use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Models\Killmails;
use Psr\Http\Message\ResponseInterface;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;

class Query extends Controller
{
    private const VALID_FILTERS = ['$gt', '$gte', '$lt', '$lte', '$eq', '$ne', '$in', '$nin', '$exists'];
    private const VALID_FIELDS = [
        'killmail_id', 'dna', 'is_npc', 'is_solo', 'point_value', 'region_id', 'system_id',
        'system_security', 'total_value', 'war_id', 'kill_time',
        'victim.ship_id', 'victim.ship_group_id', 'victim.character_id', 'victim.corporation_id',
        'victim.alliance_id', 'victim.faction_id',
        'attackers.ship_id', 'attackers.ship_group_id', 'attackers.character_id',
        'attackers.corporation_id', 'attackers.alliance_id', 'attackers.faction_id',
        'attackers.weapon_type_id',
        'item.type_id', 'item.group_id'
    ];
    private const VALID_OPTIONS = ['sort', 'limit', 'skip', 'projection'];

    public function __construct(
        protected Killmails $killmails,
        protected Cache $cache,
    ) {
    }

    protected function generateQuery(array $input): array
    {
        $query = [
            'filter' => [],
            'options' => [
                'projection' => [
                    '_id' => 0,
                    'last_modified' => 0,
                    'kill_time_str' => 0,
                    'emitted' => 0,
                ]
            ]
        ];

        if (isset($input['filter']) && is_array($input['filter'])) {
            foreach ($input['filter'] as $key => $value) {
                if (in_array($key, self::VALID_FIELDS)) {
                    $query['filter'][$key] = $this->validateFilter($key, $value);
                } else {
                    throw new InvalidArgumentException("Invalid filter field: $key");
                }
            }
        }

        if (isset($input['options']) && is_array($input['options'])) {
            $validatedOptions = $this->validateOptions($input['options']);

            // Handle projection separately
            if (isset($validatedOptions['projection'])) {
                // If user specified fields to include, we switch to inclusion mode
                if (array_search(1, $validatedOptions['projection']) !== false) {
                    $query['options']['projection'] = ['_id' => 0];  // Start with excluding _id
                    foreach ($validatedOptions['projection'] as $field => $include) {
                        if ($include) {
                            $query['options']['projection'][$field] = 1;
                        }
                    }
                } else {
                    // Otherwise, we're in exclusion mode, so we merge with existing exclusions
                    $query['options']['projection'] = array_merge(
                        $query['options']['projection'],
                        $validatedOptions['projection']
                    );
                }
                unset($validatedOptions['projection']);
            }

            // Merge other options
            $query['options'] = array_merge($query['options'], $validatedOptions);
        }

        return $query;
    }

    private function validateFilter(string $key, $value): mixed
    {
        if (str_contains($key, '_id') && (!is_int($value) || $value < 0)) {
            throw new InvalidArgumentException("Invalid value for $key: must be a non-negative integer");
        }

        if (is_array($value)) {
            $validatedValue = [];
            foreach ($value as $operator => $operand) {
                if (in_array($operator, self::VALID_FILTERS)) {
                    $validatedValue[$operator] = $operand;
                } else {
                    throw new InvalidArgumentException("Invalid filter operator: $operator");
                }
            }
            return $validatedValue;
        }
        return $value;
    }

    private function validateOptions(array $options): array
    {
        $validatedOptions = [];
        foreach ($options as $key => $value) {
            if (!in_array($key, self::VALID_OPTIONS)) {
                throw new InvalidArgumentException("Invalid option: $key");
            }

            switch ($key) {
                case 'sort':
                    if (is_array($value)) {
                        foreach ($value as $field => $direction) {
                            if (!in_array($field, self::VALID_FIELDS)) {
                                throw new InvalidArgumentException("Invalid sort field: $field");
                            }
                            if (!in_array($direction, ['asc', 'desc', 1, -1])) {
                                throw new InvalidArgumentException("Invalid sort direction for $field: $direction. Must be 'asc', 'desc', 1, or -1");
                            }
                            $validatedOptions[$key][$field] = $direction === 'asc' || $direction === 1 ? 1 : -1;
                        }
                    } elseif (is_string($value) && in_array($value, self::VALID_FIELDS)) {
                        $validatedOptions[$key] = $value;
                    } else {
                        throw new InvalidArgumentException("Invalid sort value: " . json_encode($value));
                    }
                    break;
                case 'limit':
                case 'skip':
                    if (!is_int($value) || $value < 0) {
                        throw new InvalidArgumentException("Invalid $key: $value. Must be a non-negative integer");
                    }
                    $validatedOptions[$key] = $value;
                    break;
                case 'projection':
                    if (!is_array($value)) {
                        throw new InvalidArgumentException("Invalid projection: must be an array");
                    }
                    foreach ($value as $field => $include) {
                        if (!in_array($include, [0, 1], true)) {
                            throw new InvalidArgumentException("Invalid projection value for $field: must be 0 or 1");
                        }
                    }
                    $validatedOptions[$key] = $value;
                    break;
                default:
                    $validatedOptions[$key] = $value;
            }
        }
        return $validatedOptions;
    }

    protected function prepareQueryResult(array $data): array
    {
        foreach ($data as $key => $value) {
            // Convert kill_time to Unix timestamp
            if ($key === 'kill_time') {
                if ($value instanceof UTCDateTime) {
                    $data[$key] = $value->toDateTime()->getTimestamp();
                } elseif (is_array($value) && isset($value['$date']['$numberLong'])) {
                    $data[$key] = (int)($value['$date']['$numberLong'] / 1000); // Convert milliseconds to seconds
                }
            }
            // Handle nested arrays
            elseif (is_array($value)) {
                $data[$key] = $this->prepareQueryResult($value);
            }
        }

        return $data;
    }

    #[RouteAttribute('/query[/]', ['POST'], 'Query the API for killmails')]
    public function query(): ResponseInterface
    {
        $postData = json_validate($this->getBody())
            ? json_decode($this->getBody(), true)
            : [];

        if (empty($postData)) {
            return $this->json(["error" => "No data provided"], 400);
        }

        $cacheKey = $this->cache->generateKey("query", json_encode($postData));

        try {
            $query = $this->generateQuery($postData);
            $pipeline = $this->buildAggregatePipeline($query);

            if ($this->cache->exists($cacheKey)) {
                $cachedData = $this->cache->get($cacheKey);
                // Convert cached data to ensure kill_time is properly formatted
                $cachedData = array_map([$this, 'prepareQueryResult'], $cachedData);
                $cursor = new ArrayIterator($cachedData);
            } else {
                $cursor = $this->killmails->collection->aggregate($pipeline);

                // Cache the results
                $results = iterator_to_array($cursor);
                $this->cache->set($cacheKey, $results, 300); // Cache for 5 minutes

                // Reset the cursor for streaming
                $cursor = new ArrayIterator($results);
            }

            // Stream the results
            return $this->prepareAndStreamResults($cursor);
        } catch (InvalidArgumentException $e) {
            return $this->json(["error" => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(["error" => $e->getMessage()], 500);
        }
    }

    protected function prepareAndStreamResults($cursor): ResponseInterface
    {
        $response = $this->response->withHeader('Content-Type', 'application/json');
        $body = $response->getBody();

        $body->write('[');
        $first = true;

        foreach ($cursor as $document) {
            $preparedDocument = $this->prepareQueryResult($document);
            if (!$first) {
                $body->write(',');
            }
            $body->write(json_encode($preparedDocument));
            $first = false;
        }

        $body->write(']');

        return $response;
    }

    private function buildAggregatePipeline(array $query): array
    {
        $pipeline = [];

        // $match stage
        if (!empty($query['filter'])) {
            $pipeline[] = ['$match' => $query['filter']];
        }

        // $sort stage
        if (isset($query['options']['sort'])) {
            $pipeline[] = ['$sort' => $query['options']['sort']];
        }

        // $skip stage
        if (isset($query['options']['skip'])) {
            $pipeline[] = ['$skip' => $query['options']['skip']];
        }

        // $limit stage
        if (isset($query['options']['limit'])) {
            $pipeline[] = ['$limit' => $query['options']['limit']];
        }

        // $project stage
        if (isset($query['options']['projection'])) {
            $pipeline[] = ['$project' => $query['options']['projection']];
        }

        return $pipeline;
    }
}
