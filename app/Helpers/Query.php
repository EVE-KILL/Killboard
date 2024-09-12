<?php

namespace EK\Helpers;

use EK\Models\Killmails;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;

class Query
{
    private const VALID_FILTERS = ['$gt', '$gte', '$lt', '$lte', '$eq', '$ne', '$in', '$nin', '$exists', '$or'];
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
    ) {
    }

    public function generateSimpleQuery(array $input): array
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

        if (isset($input['filter'])) {
            $query['filter'] = $this->parseSimpleFilter($input['filter']);
        }

        if (isset($input['filter']['involved_entities'])) {
            $involvedEntitiesFilter = [];
            foreach ($input['filter']['involved_entities'] as $entity) {
                $entityFilter = $this->parseInvolvedEntity($entity);
                if (!empty($entityFilter)) {
                    $involvedEntitiesFilter[] = $entityFilter;
                }
            }
            if (count($involvedEntitiesFilter) === 1) {
                $query['filter'] = array_merge($query['filter'], $involvedEntitiesFilter[0]);
            } elseif (count($involvedEntitiesFilter) > 1) {
                $query['filter']['$and'] = $involvedEntitiesFilter;
            }
        }

        if (isset($input['options'])) {
            if (isset($input['options']['sort'])) {
                $query['options']['sort'] = $this->validateSort($input['options']['sort']);
            }
            if (isset($input['options']['skip'])) {
                $query['options']['skip'] = $this->validateSkip($input['options']['skip']);
            }
            if (isset($input['options']['limit'])) {
                $query['options']['limit'] = $this->validateLimit($input['options']['limit']);
            }
            if (isset($input['options']['projection'])) {
                $query['options']['projection'] = array_merge(
                    $query['options']['projection'],
                    $this->validateProjection($input['options']['projection'])
                );
            }
        }

        $pipeline = [];
        if (!empty($query['filter'])) {
            $pipeline[] = ['$match' => $query['filter']];
        }

        if (isset($query['options']['sort'])) {
            $pipeline[] = ['$sort' => $query['options']['sort']];
        }

        if (isset($query['options']['skip'])) {
            $pipeline[] = ['$skip' => $query['options']['skip']];
        }

        if (isset($query['options']['limit'])) {
            $pipeline[] = ['$limit' => $query['options']['limit']];
        }

        $pipeline[] = ['$project' => $query['options']['projection']];

        $pagination = [
            'totalCount' => -1,
            'limit' => $query['options']['limit'] ?? 1000,
            'page' => 1
        ];

        if (!empty($query['filter'])) {
            $pagination['totalCount'] = $this->getQueryCount($query['filter']);
            $pagination['page'] = floor(($query['options']['skip'] ?? 0) / $pagination['limit']) + 1;
        }

        return [
            'query' => $query,
            'pipeline' => $pipeline,
            'pagination' => $pagination
        ];
    }

    public function generateComplexQuery(array $input): array
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
            try {
                $query['filter'] = $this->validateFilter($input['filter']);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException("Error in filter: " . $e->getMessage());
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

        $pipeline = [];
        if (!empty($query['filter'])) {
            $pipeline[] = ['$match' => $query['filter']];
        }
        if (isset($query['options']['sort'])) {
            $pipeline[] = ['$sort' => $query['options']['sort']];
        }
        if (isset($query['options']['skip'])) {
            $pipeline[] = ['$skip' => $query['options']['skip']];
        }
        if (isset($query['options']['limit'])) {
            $pipeline[] = ['$limit' => $query['options']['limit']];
        }
        if (isset($query['options']['projection'])) {
            $pipeline[] = ['$project' => $query['options']['projection']];
        }

        $pagination = [
            'totalCount' => -1,
            'limit' => $query['options']['limit'] ?? 1000,
            'page' => 1
        ];

        if (!empty($query['filter'])) {
            $pagination['totalCount'] = $this->getQueryCount($query['filter']);
            $pagination['page'] = floor(($query['options']['skip'] ?? 0) / $pagination['limit']) + 1;
        }

        return [
            'query' => $query,
            'pipeline' => $pipeline,
            'pagination' => $pagination
        ];
    }

    public function getQueryCount(array $filter): int
    {
        return $this->killmails->count($filter);
    }

    private function parseSimpleFilter(array $filter): array
    {
        $parsedFilter = [];

        foreach ($filter as $key => $value) {
            switch ($key) {
                case 'killmail_id':
                case 'region_id':
                case 'system_id':
                case 'war_id':
                    if ($value !== null) {
                        $parsedFilter[$key] = $this->validatePositiveInteger($value, $key);
                    }
                    break;
                case 'is_npc':
                case 'is_solo':
                    if ($value !== null) {
                        $parsedFilter[$key] = (bool)$value;
                    }
                    break;
                case 'system_security':
                case 'total_value':
                    $parsedFilter[$key] = $this->parseRangeFilter($value, $key);
                    break;
                case 'kill_time':
                    $parsedFilter[$key] = $this->parseKillTimeFilter($value);
                    break;
            }
        }

        return $parsedFilter;
    }

    private function parseRangeFilter(array $range, string $field): array
    {
        $filter = [];
        if (isset($range['lowest']) && $range['lowest'] !== null) {
            $filter['$gte'] = $this->validateNumber($range['lowest'], $field . '.lowest');
        }
        if (isset($range['highest']) && $range['highest'] !== null) {
            $filter['$lte'] = $this->validateNumber($range['highest'], $field . '.highest');
        }
        return $filter;
    }

    private function parseKillTimeFilter(array $range): array
    {
        $filter = [];
        if (isset($range['lowest']) && $range['lowest'] !== null) {
            $filter['$gte'] = new UTCDateTime($this->validatePositiveInteger($range['lowest'], 'kill_time.lowest') * 1000);
        }
        if (isset($range['highest']) && $range['highest'] !== null) {
            $filter['$lte'] = new UTCDateTime($this->validatePositiveInteger($range['highest'], 'kill_time.highest') * 1000);
        }
        return $filter;
    }

    private function parseInvolvedEntity(array $entity): array
    {
        $idField = $entity['entity_type'] . '_id';
        $victimField = 'victim.' . $idField;
        $attackerField = 'attackers.' . $idField;

        $filter = [];

        if ($entity['involved_as'] === 'both') {
            $filter['$or'] = [
                [$victimField => $entity['entity_id']],
                [$attackerField => $entity['entity_id']]
            ];
        } elseif ($entity['involved_as'] === 'victim') {
            $filter[$victimField] = $entity['entity_id'];
        } elseif ($entity['involved_as'] === 'attacker') {
            $filter[$attackerField] = $entity['entity_id'];
        }

        foreach (['ship_id', 'ship_group_id', 'weapon_type_id'] as $field) {
            if (isset($entity[$field]) && $entity[$field] !== null) {
                if ($entity['involved_as'] === 'both') {
                    $filter['$or'][0]['victim.' . $field] = $entity[$field];
                    $filter['$or'][1]['attackers.' . $field] = $entity[$field];
                } else {
                    $prefix = $entity['involved_as'] === 'victim' ? 'victim.' : 'attackers.';
                    $filter[$prefix . $field] = $entity[$field];
                }
            }
        }

        return $filter;
    }

    private function validateFilter($filter): array
    {
        $validatedFilter = [];

        foreach ($filter as $key => $value) {
            try {
                if ($key === '$or') {
                    if (!is_array($value)) {
                        throw new InvalidArgumentException("Invalid \$or operator: value must be an object");
                    }
                    $orConditions = [];
                    foreach ($value as $orKey => $orValue) {
                        if (!in_array($orKey, self::VALID_FIELDS)) {
                            throw new InvalidArgumentException("Invalid field in \$or condition: $orKey");
                        }
                        $orConditions[] = [$orKey => $this->validateFilterValue($orKey, $orValue)];
                    }
                    $validatedFilter[$key] = $orConditions;
                } elseif (in_array($key, self::VALID_FIELDS)) {
                    $validatedFilter[$key] = $this->validateFilterValue($key, $value);
                } else {
                    throw new InvalidArgumentException("Invalid filter field: $key");
                }
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException("Error in filter: " . $e->getMessage());
            }
        }

        return $validatedFilter;
    }

    private function validateFilterValue(string $key, $value): mixed
    {
        if (str_contains($key, '_id') && is_numeric($value)) {
            return (int)$value; // Convert to integer for ID fields
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
                    $validatedOptions[$key] = $this->validateSort($value);
                    break;
                case 'limit':
                    $validatedOptions[$key] = $this->validateLimit($value);
                    break;
                case 'skip':
                    $validatedOptions[$key] = $this->validateSkip($value);
                    break;
                case 'projection':
                    $validatedOptions[$key] = $this->validateProjection($value);
                    break;
                default:
                    $validatedOptions[$key] = $value;
            }
        }
        return $validatedOptions;
    }

    private function validatePositiveInteger($value, string $field): int
    {
        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        if ($intValue === false || $intValue < 0) {
            throw new InvalidArgumentException("$field must be a positive integer");
        }
        return $intValue;
    }

    private function validateNumber($value, string $field): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("$field must be a number");
        }
        return (float)$value;
    }

    private function validateSort($sort): array
    {
        if (!is_array($sort)) {
            throw new InvalidArgumentException("Sort must be an array");
        }
        foreach ($sort as $field => $direction) {
            if (!in_array($field, self::VALID_FIELDS)) {
                throw new InvalidArgumentException("Invalid sort field: $field");
            }
            if (!in_array($direction, [1, -1, 'asc', 'desc'])) {
                throw new InvalidArgumentException("Invalid sort direction for $field: $direction. Must be 'asc', 'desc', 1, or -1");
            }
            $sort[$field] = $direction === 'asc' || $direction === 1 ? 1 : -1;
        }
        return $sort;
    }

    private function validateSkip($skip): int
    {
        return $this->validatePositiveInteger($skip, 'skip');
    }

    private function validateLimit($limit): int
    {
        $intValue = filter_var($limit, FILTER_VALIDATE_INT);
        if ($intValue === false || $intValue < 1) {
            return 1000; // Default to 1000 if the limit is invalid
        }
        return min($intValue, 1000); // Ensure the limit doesn't exceed 1000
    }

    private function validateProjection($projection): array
    {
        if (!is_array($projection)) {
            throw new InvalidArgumentException("Projection must be an array");
        }
        foreach ($projection as $field => $include) {
            if (!in_array($include, [0, 1], true)) {
                throw new InvalidArgumentException("Invalid projection value for $field: must be 0 or 1");
            }
        }
        return $projection;
    }
}
