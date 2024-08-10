<?php

namespace EK\Helpers;

use EK\Cache\Cache;
use EK\Models\Killmails;

class Query
{
    public array $validQueryParams = [
        'kill_time' => 'datetime', 'system_id' => 'int', 'region_id' => 'int',
        'ship_value' => 'float', 'fitting_value' => 'float', 'total_value' => 'float', 'is_npc' => 'bool',
        'is_solo' => 'bool', 'victim.ship_id' => 'int', 'victim.character_id' => 'int',
        'victim.corporation_id' => 'int', 'victim.alliance_id' => 'int', 'victim.faction_id' => 'int',
        'attackers.ship_id' => 'int', 'attackers.weapon_type-id' => 'int', 'attackers.character_id' => 'int',
        'attackers.corporation_id' => 'int', 'attackers.alliance_id' => 'int', 'attackers.faction_id' => 'int',
        'attackers.final_blow' => 'int', 'items.type_id' => 'int', 'items.group_id' => 'int', 'items.category_id' => 'int'
    ];
    public array $validSortParams = [
        'page' => 'int', 'limit' => 'int', 'offset' => 'int', 'order' => 'string',
    ];

    public function __construct(
        protected Cache $cache,
        protected Killmails $killmails
    ) {
    }

    protected function generateQuery(array $parameters): array
    {
        $queryArray = [];
        $dataArray = [];

        if (!empty($parameters)) {
            foreach (array_keys($this->validQueryParams) as $arg) {
                if (isset($parameters[$arg])) {
                    $dataArray[$arg] = $parameters[$arg];
                }
            }
        }

        // Limit
        $queryArray['limit'] = $parameters['limit'] ?? 1000;
        // Order
        $queryArray['sort'] = ['kill_time' => $parameters['order'] === 'DESC' ? -1 : 1];
        // Offset
        if (isset($parameters['offset']) && $parameters['offset'] > 0) {
            $queryArray['skip'] = $parameters['offset'];
        }
        // Remove _id
        $queryArray['projection'] = ['_id' => 0];

        // Return the query
        return ['filter' => $dataArray, 'options' => $queryArray];
    }

    protected function executeAggregateQuery(array $filterCriteria, array $parameters): array
    {
        // Generate the query and options
        $query = $this->generateQuery($parameters);

        // Generate a cache key
        $cacheKey = $this->cache->generateKey($filterCriteria, $parameters);

        // Check if the result is cached
        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $cacheResult;
        }

        // Build the aggregation pipeline dynamically
        $pipeline = [
            ['$match' => array_merge($filterCriteria, $query['filter'])],
            ['$sort' => $query['options']['sort']],
            ['$limit' => $query['options']['limit']],
        ];

        // Include skip stage if offset is provided
        if (isset($query['options']['skip'])) {
            $pipeline[] = ['$skip' => $query['options']['skip']];
        }

        // Projection stage to remove '_id'
        $pipeline[] = ['$project' => $query['options']['projection']];

        // Execute the aggregation
        $result = $this->killmails->aggregate($pipeline)->toArray();

        // Cache the result
        $cacheTime = 3600; // Cache time in seconds (1 hour)
        $this->cache->set($cacheKey, $result, $cacheTime);

        return $result;
    }

    public function getByField(string $field, $value, array $parameters = []): array
    {
        return $this->executeAggregateQuery([$field => $value], $parameters);
    }

    public function getBetweenValues(string $field, $minValue, $maxValue, array $parameters = []): array
    {
        $rangeCriteria = [
            $field => [
                '$gte' => $minValue,
                '$lte' => $maxValue,
            ],
        ];

        return $this->executeAggregateQuery($rangeCriteria, $parameters);
    }

    public function getLessThanValue(string $field, $value, array $parameters = []): array
    {
        return $this->executeAggregateQuery([$field => ['$lte' => $value]], $parameters);
    }

    public function getMoreThanValue(string $field, $value, array $parameters = []): array
    {
        return $this->executeAggregateQuery([$field => ['$gte' => $value]], $parameters);
    }

    public function getBetweenDates(string $startDate, string $endDate, array $parameters = []): array
    {
        $dateRangeCriteria = [
            'kill_time' => [
                '$gte' => new \MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000),
                '$lte' => new \MongoDB\BSON\UTCDateTime(strtotime($endDate) * 1000)
            ]
        ];

        return $this->executeAggregateQuery($dateRangeCriteria, $parameters);
    }

    // Reusable field queries
    public function getBySystemId(int $systemId, array $parameters = []): array
    {
        return $this->getByField('system_id', $systemId, $parameters);
    }

    public function getByRegionId(int $regionId, array $parameters = []): array
    {
        return $this->getByField('region_id', $regionId, $parameters);
    }

    public function getByCharacterId(int $characterId, array $parameters = []): array
    {
        return $this->getByField('victim.character_id', $characterId, $parameters);
    }

    public function getByCorporationId(int $corporationId, array $parameters = []): array
    {
        return $this->getByField('victim.corporation_id', $corporationId, $parameters);
    }

    public function getByAllianceId(int $allianceId, array $parameters = []): array
    {
        return $this->getByField('victim.alliance_id', $allianceId, $parameters);
    }

    public function getByFactionId(int $factionId, array $parameters = []): array
    {
        return $this->getByField('victim.faction_id', $factionId, $parameters);
    }

    public function getByWeaponTypeId(int $weaponTypeId, array $parameters = []): array
    {
        return $this->getByField('attackers.weapon_type-id', $weaponTypeId, $parameters);
    }

    public function getByShipId(int $shipId, array $parameters = []): array
    {
        return $this->getByField('victim.ship_id', $shipId, $parameters);
    }

    // Date range queries
    public function getAfterDate(string $startDate, array $parameters = []): array
    {
        return $this->executeAggregateQuery([
            'kill_time' => [
                '$gte' => new \MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000)
            ]
        ], $parameters);
    }

    public function getBeforeDate(string $endDate, array $parameters = []): array
    {
        return $this->executeAggregateQuery([
            'kill_time' => [
                '$lte' => new \MongoDB\BSON\UTCDateTime(strtotime($endDate) * 1000)
            ]
        ], $parameters);
    }

    // Value-based queries
    public function totalValueLess(float $value, array $parameters = []): array
    {
        return $this->getLessThanValue('total_value', $value, $parameters);
    }

    public function totalValueMore(float $value, array $parameters = []): array
    {
        return $this->getMoreThanValue('total_value', $value, $parameters);
    }

    public function totalValueBetween(float $minValue, float $maxValue, array $parameters = []): array
    {
        return $this->getBetweenValues('total_value', $minValue, $maxValue, $parameters);
    }

    public function shipValueLess(float $value, array $parameters = []): array
    {
        return $this->getLessThanValue('ship_value', $value, $parameters);
    }

    public function shipValueMore(float $value, array $parameters = []): array
    {
        return $this->getMoreThanValue('ship_value', $value, $parameters);
    }

    public function shipValueBetween(float $minValue, float $maxValue, array $parameters = []): array
    {
        return $this->getBetweenValues('ship_value', $minValue, $maxValue, $parameters);
    }

    public function pointValueLess(float $value, array $parameters = []): array
    {
        return $this->getLessThanValue('point_value', $value, $parameters);
    }

    public function pointValueMore(float $value, array $parameters = []): array
    {
        return $this->getMoreThanValue('point_value', $value, $parameters);
    }

    public function pointValueBetween(float $minValue, float $maxValue, array $parameters = []): array
    {
        return $this->getBetweenValues('point_value', $minValue, $maxValue, $parameters);
    }
}
