<?php

namespace EK\Helpers;

use EK\Models\Killmails;
use InvalidArgumentException;

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
            'involved_entities_matches' => [],
            'options' => []
        ];

        if (isset($input['filter'])) {
            $query['filter'] = $this->parseSimpleFilter($input['filter']);
        }

        if (isset($input['filter']['involved_entities'])) {
            foreach ($input['filter']['involved_entities'] as $entity) {
                $query['involved_entities_matches'][] = ['$match' => $this->parseInvolvedEntity($entity)];
            }
        }

        if (isset($input['options'])) {
            $query['options'] = $input['options'];
        }

        return $query;
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

        return $query;
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

    private function parseSimpleFilter(array $filter): array
    {
        $parsedFilter = [];

        foreach ($filter as $key => $value) {
            switch ($key) {
                case 'killmail_id':
                case 'is_npc':
                case 'is_solo':
                case 'region_id':
                case 'system_id':
                case 'war_id':
                    if ($value !== null) {
                        $parsedFilter[$key] = $value;
                    }
                    break;
                case 'system_security':
                case 'total_value':
                case 'kill_time':
                    $parsedFilter[$key] = $this->parseRangeFilter($value);
                    break;
            }
        }

        return $parsedFilter;
    }

    private function parseRangeFilter(array $range): array
    {
        $filter = [];
        if (isset($range['lowest']) && $range['lowest'] !== null) {
            $filter['$gte'] = $range['lowest'];
        }
        if (isset($range['highest']) && $range['highest'] !== null) {
            $filter['$lte'] = $range['highest'];
        }
        return $filter;
    }

    private function parseInvolvedEntity(array $entity): array
    {
        $filter = [];
        $idField = $entity['entity_type'] . '_id';

        if ($entity['involved_as'] === 'both' || $entity['involved_as'] === 'victim') {
            $filter['victim.' . $idField] = $entity['entity_id'];
        }

        if ($entity['involved_as'] === 'both' || $entity['involved_as'] === 'attacker') {
            $filter['attackers.' . $idField] = $entity['entity_id'];
        }

        foreach (['ship_id', 'ship_group_id', 'weapon_type_id'] as $field) {
            if (isset($entity[$field]) && $entity[$field] !== null) {
                $prefix = $entity['involved_as'] === 'victim' ? 'victim.' : 'attackers.';
                $filter[$prefix . $field] = $entity[$field];
            }
        }

        return $filter;
    }

    private function parseOptions(array $options): array
    {
        $pipeline = [];

        if (isset($options['sort'])) {
            $pipeline[] = ['$sort' => $options['sort']];
        }

        if (isset($options['skip'])) {
            $pipeline[] = ['$skip' => $options['skip']];
        }

        if (isset($options['limit'])) {
            $pipeline[] = ['$limit' => $options['limit']];
        }

        if (isset($options['projection'])) {
            $pipeline[] = ['$project' => $options['projection']];
        }

        return $pipeline;
    }
}
